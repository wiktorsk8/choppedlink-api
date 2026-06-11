# Sprawozdanie — ChoppedLink (URL shortener)

Projekt na zaliczenie z zaawansowanych baz danych. Opis na niskim poziomie tego, **jak aplikacja
komunikuje się z bazami danych**: PostgreSQL (write model / źródło prawdy) oraz Redis (read model /
projekcja), w architekturze CQRS.

---

## 1. Opis projektu i architektury

### Co robi system

API skracacza URL. Trzy operacje istotne dla bazy:

- **utworzenie linku** — generujemy `slug`, zapisujemy mapowanie `slug -> url`, opcjonalnie limit
  wejść (`accessLimit`);
- **przekierowanie** — `GET /{slug}` rozwiązuje `slug → url`, zlicza wejście (klik) i — jeśli link
  ma limit — pilnuje, żeby go nie przekroczyć;
- **statystyki** — odczyt liczby kliknięć danego linku.

### Warstwy i podział

Modularny monolit z warstwami DDD. Dwa rooty PSR-4:

- `App\` → `src/` — warstwa dostarczania (kontrolery, DTO żądań HTTP, framework);
- `Module\` → `modules/` — domena, podzielona na konteksty (`ShortLink`, `User`, `Shared`),
  a w każdym: `Domain/` (encje, zdarzenia, interfejsy repozytoriów), `Application/` (komendy,
  zapytania, porty, serwisy), `Infrastructure/` (Doctrine, Redis, adaptery).

Na to nałożone jest **CQRS** — rozdzielenie strony zapisu (commands) od strony odczytu (queries):

- **write model** to agregat `ShortLink` w PostgreSQL, modyfikowany komendami przez busa
  `command.bus` (Symfony Messenger) w transakcji;
- **read model** to zdenormalizowana projekcja w Redisie, czytana zapytaniami z pominięciem
  hydracji encji.

### Dlaczego CQRS dobrze pasuje do tego problemu

CQRS nie zawsze się opłaca — dokłada infrastruktury. Tutaj opłaca się z dwóch konkretnych powodów:

1. **Silna asymetria i podwójny charakter ruchu.** Przekierowania to ruch dominujący — to jest
   *heavy read*. Jednocześnie **każde** przekierowanie to też zapis (zliczenie klika, a przy linkach
   z limitem dekrementacja „budżetu" wejść) — więc *heavy write* na tej samej ścieżce. Sam odczyt
   jest prosty: wyszukanie `slug -> url`. Rozjazd profili (dużo prostych odczytów + zapis przy
   każdym z nich) to klasyczna sytuacja, w której chce się **inaczej obsłużyć stronę odczytu i
   zapisu**.

Dzięki rozdzieleniu obie strony można też **skalować niezależnie** (więcej replik Redisa pod
odczyt, bez ruszania bazy zapisu).

### Przepływ przekierowania (uproszczony)

```
GET /{slug}
   │
   ▼
Kontroler ──dispatch──► RegisterShortLinkClick           (command.bus, SYNC, w transakcji)
   │                       ├─ odczyt z Redisa (slug → url, isLimited)   [cache hit #1]
   │                       ├─ jeśli limit: SELECT ... FOR UPDATE + recordAccess() + zapis
   │                       ├─ INSERT do short_link_click (Postgres, trwały log)
   │                       └─ HINCRBY clicks (Redis, szybki licznik)
   │
   ├─ odczyt z Redisa (slug → url) [cache hit #2, po URL do redirectu]
   ▼
302 Redirect → url
```

W najgorszym razie są to **2 trafienia w cache** - komenda czyta z Redisa, żeby sprawdzić, czy istnieje limit i poprawnie go obsłużyć. Następnie ponownie w kontrolerze czytamy slug z cache, żeby zwrócić wynik jako response. Takim podejściem zachowujemy spójność z zasadami CQRS (komenda to zawsze void) kosztem dodatkowego odpytania Redisa.

---

## 2. Bazy danych: PostgreSQL i Redis — role

### Kto jest czym

| | PostgreSQL | Redis |
|---|---|---|
| Rola | **write model + źródło prawdy** | **read model / projekcja** |
| Trzyma | `short_links` (agregat), `short_link_click` (trwały log klików) | hash `shortlink:{slug}` + indeks `shortlink:id:{id}` |
| Autorytatywność | tak — jedyne źródło prawdy | nie — w całości odtwarzalny z Postgresa |
| Optymalizowany pod | spójność, trwałość, inwarianty | szybkość odczytu |

**Postgres jest źródłem prawdy.** Wszystko, co musi przetrwać i być spójne, żyje tutaj:
definicja linku, licznik wejść pod limit (`access_counter`) oraz trwały log kliknięć
(`short_link_click`, jeden wiersz na klik).


Projekcja powstaje na dwa sposoby:

- **dane linku** — przy utworzeniu linku zdarzenie domenowe `ShortLinkCreated` jest obsługiwane
  przez projektor (`ShortLinkProjector`), który zapisuje hash w Redisie;
- **licznik klików** — inkrementowany przy każdej rejestracji wejścia (`HINCRBY`), a jego trwałym
  odpowiednikiem jest liczba wierszy w `short_link_click`.

### ACID PostgreSQL i jak go wykorzystujemy

PostgreSQL daje pełne gwarancje ACID, a my opieramy na nich egzekucję inwariantu limitu:

- **A (atomowość)** — każda komenda biegnie w jednej transakcji. Magistrala `command.bus` ma
  middleware `doctrine_transaction`: otwiera transakcję przed handlerem, robi `flush` i `commit` po
  nim, a przy wyjątku — `rollback`. Czyli „sprawdź limit + zwiększ licznik + dopisz wiersz klika"
  jest albo całe, albo wcale.
- **C (spójność)** — więzy bazy (klucze, typy) plus inwariant domenowy (`accessLimit`) są
  utrzymywane przy każdym commitcie.
- **I (izolacja)** — kluczowa przy limicie: blokada `FOR UPDATE` (patrz sekcja 3) serializuje
  współbieżne wejścia na tym samym linku, więc nie da się przekroczyć limitu wyścigiem.
- **D (trwałość)** — po commitcie dane są na dysku (WAL). To dlatego Postgres jest źródłem prawdy,
  a Redis może być ulotny.

---

## 3. Internale zapisu — PostgreSQL

Najciekawsza część: dlaczego dwa rodzaje „liczenia" traktujemy zupełnie inaczej.

### 3.1. `access_counter` (limit) — wymaga `SELECT ... FOR UPDATE`

Rejestracja wejścia pod limit to **read-modify-write wykonywany w aplikacji**:

```
1. odczytaj aktualny access_counter i access_limit
2. w PHP: jeśli access_counter >= access_limit → odrzuć (CannotAccessUrlException)
3. w przeciwnym razie zapisz access_counter + 1
```

Decyzja („czy wolno wejść?") zapada **w kodzie aplikacji**, pomiędzy odczytem a zapisem. To otwiera
okno na wyścig: dwa równoległe żądania na tym samym linku odczytają tę samą wartość licznika, oba
uznają, że limit nie jest przekroczony, i oba zapiszą +1 — efekt to **lost update** i przekroczenie
limitu.

Rozwiązaniem jest **blokada pesymistyczna** na czas transakcji. W repozytorium:

```php
// modules/ShortLink/Infrastructure/Persistence/Doctrine/Repository/ShortLinkRepository.php
$this->createQueryBuilder('s')
    ->andWhere('s.slug = :slug')
    ->setParameter('slug', $slug)
    ->getQuery()
    ->setLockMode(LockMode::PESSIMISTIC_WRITE)   // → SQL: ... FOR UPDATE
    ->getOneOrNullResult();
```

Doctrine generuje wtedy:

```sql
SELECT ... FROM short_links WHERE slug = ? FOR UPDATE;
```

`FOR UPDATE` zakłada blokadę na wybranym wierszu do końca transakcji. Drugie żądanie, próbujące
zablokować ten sam wiersz, **czeka**, aż pierwsze zatwierdzi i zwolni blokadę — dopiero wtedy
odczytuje już zaktualizowaną wartość. Operacje „odczytaj → sprawdź → zapisz" są w ten sposób
**zserializowane**, a inwariant limitu jest nie do złamania nawet przy współbieżności.

Całość działa, bo handler komendy biegnie w transakcji magistrali (`doctrine_transaction`) — blokada
jest ważna tylko wewnątrz transakcji, a inkrement zostaje utrwalony przy commitcie.

```php
// RegisterShortLinkClickHandler — fragment ścieżki z limitem
if ($target->isLimited) {
    $shortLink = $this->repository->lockBySlug($slug); // SELECT ... FOR UPDATE
    $shortLink->recordAccess();                        // sprawdza limit, ++access_counter
    $this->repository->save($shortLink);               // flush+commit robi middleware
}
```

### 3.2. Klik — „zwykły" zapis, bez blokady

Zliczenie klika jest fundamentalnie inne, bo **nie ma decyzji w aplikacji** - inkrement jest
bezwarunkowy. Nie trzeba odczytywać poprzedniej wartości, żeby zdecydować, czy wolno zapisać. A
skoro nie ma read-modify-write po stronie aplikacji, nie ma czego serializować blokadą.

Taki bezwarunkowy inkrement można zrobić na dwa lock-free sposoby:

- **atomowe pojedyncze polecenie silnika** — `UPDATE short_links SET clicks = clicks + 1 WHERE ...`
  albo redisowe `HINCRBY`. Całe „przeczytaj i dodaj" dzieje się wewnątrz silnika, w jednym poleceniu;
  aplikacja nie trzyma wartości pośredniej, więc nie potrzebuje jawnego `FOR UPDATE`;
- **append-only INSERT** (nasze rozwiązanie) — każdy klik to **osobny wiersz** w `short_link_click`:

```sql
INSERT INTO short_link_click (id, slug, created_at) VALUES (?, ?, ?);
```

## 4. Read model w Redisie — dlaczego i dlaczego bezpieczny bez wolumenu

### Dlaczego akurat Redis

- **Struktura danych pasuje do problemu.** Hash `shortlink:{slug}` to dosłownie „pod jednym kluczem
  wszystko o linku". Odczyt pod redirect to jedno `HGETALL` po kluczu — w pamięci, O(1).
- **Lock-free licznik.** `HINCRBY shortlink:{slug} clicks 1` to atomowy inkrement bez blokad i bez
  round-tripu odczyt→zapis po stronie aplikacji.
- **Odciążenie Postgresa.** Ruch dominujący (przekierowania) w ogóle nie dotyka bazy zapisu po stronie
  rozwiązywania URL.

### Dlaczego trzymanie tego tylko w RAM (bez wolumenu) jest bezpieczne

To wynika wprost z faktu, że Postgres to źródło prawdy i w każdej chwili możemy odtworzyć z niego stan razem z licznikiem kliknięć.

Po utracie danych z Redisa wystarczy przebudować projekcję z Postgresa:

```bash
php bin/console app:shortlink:rebuild-read-model
```

Komenda przechodzi po wszystkich linkach z `short_links`, zapisuje ich hashe w Redisie i ustawia
`clicks` z `countBySlug()` (czyli z trwałego logu). Dlatego nie potrzebujemy wolumenu ani trwałości
Redisa — trwałość już mamy, w Postgresie.

### Trade-off'y

Read model zapełnia się po utworzeniu linku lub po rebuildzie dlatego wymagane jest rebuildowanie projekcji przy każdym cold starcie.
Może być to problematyczne przy większej ilości rekordów w bazie - alternatywnie można dodać fallback przy readzie z Redisa - gdy jest 404 to odpytujemy Postgresa.

---

## 5. Warstwa sieciowa Redisa — co się dzieje i dlaczego mimo to jest błyskawiczny

### Co dokładnie dzieje się przy jednym poleceniu

1. **Połączenie.** Klient (u nas Predis) łączy się z Redisem po **TCP** (`redis://redis:6379`) albo,
   gdy oba są na tym samym hoście, po **gnieździe uniksowym**. Połączenie jest zwykle **trzymane
   otwarte i reużywane**, więc kosztu zestawiania TCP (handshake) nie płaci się na każdą komendę.
2. **Kodowanie do RESP.** Komenda jest serializowana do protokołu **RESP** (REdis Serialization
   Protocol) — prostego, tekstowo-binarnego formatu. Np. `HGETALL shortlink:abc` leci po sokecie jako:

   ```
   *2\r\n$7\r\nHGETALL\r\n$13\r\nshortlink:abc\r\n
   ```

   (tablica dwóch „bulk stringów": nazwa komendy + klucz). Odpowiedź wraca tym samym protokołem.
3. **Round-trip.** Aplikacja wysyła zakodowaną komendę i **czeka na odpowiedź** — to jeden obieg
   tam-i-z-powrotem (RTT). Czyli przekierowanie, które robi `HGETALL` + `HINCRBY` + `HGETALL`, to
   kilka takich obiegów.

Innymi słowy: koszt to **(parsowanie RESP, prawie darmowe) + (praca serwera, prawie darmowa) + (RTT
po sieci)** - gdzie ruch sieciowy to jedyny realny koszt






