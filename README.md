
# Projek Aplikasi Modular

Platform aplikasi modular dengan teras bersama dan modul perniagaan yang boleh dipasang, dikembangkan dan diurus secara berasingan.

## Current Release

**v0.3.0 — Core Readiness Baseline**

**Status: RELEASED / VERIFIED**

Core v0.3.0 telah melengkapkan asas kesediaan Core untuk seni bina aplikasi modular dan sempadan Core–Module. Release ini bukan pelepasan keseluruhan produk Pick & Drop.

### Release Verification

- 191 ujian lulus
- 712 assertions
- 0 kegagalan
- MySQL 8.x
- PHP 8.3

### Focused Test Verification

- 6 ujian pengesanan modul
- 9 assertions
- 0 kegagalan

### Release Tag

`v0.3.0`

### Release Commit

`46d16edacf96395d3f233c8c63e2cd06c4ae4b3b`

## Current Development State

| Versi | Skop | Status |
|---|---|---|
| v0.1.0 | Core Basic Foundation | RELEASED |
| v0.2.0 | Authentication, verification dan security hardening | RELEASED / VERIFIED |
| v0.3.0 | Modular architecture readiness dan Core–Module boundaries | RELEASED / VERIFIED |
| v0.3.1 | Architecture Audit & Design Lock | IN PROGRESS — NOT RELEASED |
| v0.3.2 | Core TASK Contract & Registry | PLANNED — NOT IMPLEMENTED |
| v0.4.0 | Pick & Drop Business Workflow MVP | DEFERRED |

### Latest Development Verification

- Repository: `akuhudhud/projek-aplikasi-modular`
- Branch: `main`
- Verified HEAD: `8911c8ab46b7b27e2052018447a2496f4ec518f6`
- Commit: `docs: reconcile README with current development state`
- CI Run: [#53 — success](https://github.com/akuhudhud/projek-aplikasi-modular/actions/runs/38215571674)

Keputusan ujian pada HEAD tersebut:

- Registration diagnostic: 1 test / 10 assertions
- Full feature suite: 229 tests / 955 assertions / 0 failures
- MySQL concurrency regression: 1 test / 10 assertions / 0 failures

CI #53 mengesahkan commit HEAD di atas, termasuk perubahan README ini. Bukti tersebut mengesahkan ujian automatik yang dijalankan; ia tidak dengan sendirinya meluluskan atau melepaskan v0.3.1.

### v0.3.1 — Architecture Audit & Design Lock

Kerja semasa merangkumi:

- Audit source dan daftar keputusan fail berasaskan bukti.
- Semakan integriti audit log dan lifecycle akaun.
- Semakan migration sequence dan rollback safety.
- Pengesahan ujian registration, authentication, profile, session, contact verification dan account lifecycle.
- Pengesahan Module Discovery dan sempadan Core–Module.
- Pengesahan kegagalan penghantaran OTP melalui ujian.
- Penyelarasan README, GitBook dan bukti CI.
- Semakan acceptance gates sebelum keputusan release.

Audit diff dan urutan migration terkini tidak menemui kecacatan konkrit yang memerlukan source patch segera. Semakan tersebut bukan analisis statik PHP menyeluruh atau latihan migration di production.

Kerja v0.3.1 masih memerlukan penyelesaian acceptance gates yang belum disahkan, termasuk analisis statik PHP menyeluruh dan latihan migration pada persekitaran production-like.

v0.3.1 kekal **IN PROGRESS — NOT RELEASED** sehingga semua acceptance gates disahkan dan keputusan penutupan dibuat.

## Core Scope

- Account dan identiti akaun menggunakan UUID
- Registration dan verification gate
- Authentication dan session
- Profile dan profile picture
- Account lifecycle
- Super Admin dan kawalan akaun istimewa
- Audit Log
- Security Foundation
- Generic Module Discovery
- Core–Module boundary
- Rujukan akaun modul tanpa kebergantungan terus kepada model Account dalaman Core

## OTP Delivery

Penghantaran OTP sebenar ditangguhkan sehingga peringkat akhir production readiness.

Provider SMS/e-mel sebenar, konfigurasi production dan rahsia provider tidak termasuk dalam bukti CI semasa. Ujian kegagalan provider mengesahkan bahawa kegagalan penghantaran dikendalikan dengan selamat; ia bukan bukti bahawa penghantaran OTP production telah tersedia.

## Business Module

Business Module dibangunkan secara berasingan daripada Core dengan mematuhi kontrak dan sempadan integrasi yang diluluskan.

Pick & Drop ialah Business Module pertama yang dirancang.

Asas Pick & Drop yang telah dibangunkan bukan keseluruhan workflow operasi. Runner, pengagihan tugasan, bukti pickup/delivery dan aliran operasi lengkap tidak boleh dianggap siap hanya berdasarkan release Core v0.3.0.

## Planned Core TASK Contract & Registry — v0.3.2

Skop yang dirancang selepas v0.3.1 ditutup dan batch implementation diluluskan:

- TASK registry generik dengan UUID.
- Kontrak atau adapter modul tanpa akses terus Core kepada jadual dalaman modul.
- Pendaftaran TASK idempotent berdasarkan `module_key`, `task_type` dan `source_id`.
- Task List generik dengan kawalan authorization.
- Status generik `OPEN`, `ASSIGNED`, `IN_PROGRESS`, `COMPLETED` dan `CANCELLED`.
- Primitive assignment yang hanya merekod assignment selepas pengesahan eligibility dan policy modul.

v0.3.2 masih dirancang dan belum diimplementasikan atau dilepaskan.

## Deferred Scope

Perkara berikut bukan sebahagian daripada release Core v0.3.0:

- Aliran operasi lengkap Pick & Drop
- Runner dan tuntutan tugasan
- Aplikasi Flutter
- Aplikasi web
- Enjin harga dan fi platform
- Pembayaran dan penyelesaian kewangan
- Pengagihan tugasan
- Penghantaran OTP sebenar untuk persekitaran produksi

Pick & Drop Business Workflow MVP v0.4.0 kekal ditangguhkan sehingga kerja v0.3.2 selesai, disahkan dan skop seterusnya diluluskan semula.

## Development Order

1. Backend / Core API
2. Backend Testing & Review
3. Core Readiness dan Core–Module Contract
4. Architecture Audit & Design Lock
5. Core TASK Contract & Registry
6. Pick & Drop Business Workflow MVP
7. Flutter User App
8. Web App

Urutan terperinci tertakluk kepada baseline, keputusan dan rekod pembangunan terkini dalam GitBook.

## Tech Stack

### Backend

- Laravel 13
- PHP 8.3
- MySQL 8.x

### User App

- Flutter

## Source of Truth

GitHub ialah Source of Truth untuk source code dan technical artifacts yang telah diterima.

GitBook digunakan untuk project knowledge, baseline, keputusan, requirements, architecture, audit records dan Development Activity Log.

GitHub dan GitBook hendaklah dikekalkan selari selepas setiap keputusan atau release rasmi.

## Versioning and Release Control

- v0.3.0 kekal release rasmi.
- v0.3.1 belum RELEASED.
- v0.3.2 masih PLANNED.
- v0.4.0 kekal DEFERRED.
- Jangan pindahkan, tulis semula atau mengubah tag release sejarah.
- Jangan menandakan versi sebagai RELEASED sebelum release sebenar diwujudkan dan disahkan.

## Development Control

**AUDIT → DESIGN LOCK → BATCH PLAN → IMPLEMENT → TEST → COMMIT → VERIFY → DOCUMENT → CLOSE**
