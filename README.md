Projek Aplikasi Modular

Platform aplikasi modular dengan teras bersama dan modul perniagaan yang boleh dipasang, dikembangkan dan diurus secara berasingan.

Current Release

v0.3.1 — Architecture Audit & Design Lock

Status: RELEASED / VERIFIED

Release ini memfokuskan pengesahan seni bina Core, sempadan Core–Module, audit teknikal, keselamatan dan kebolehpercayaan backend.

Release Verification

- CI Run: "#64 — success" (https://github.com/akuhudhud/projek-aplikasi-modular/actions/runs/38568276517)
- Verified CI commit: "53629b0380a1757a141968409713db7f05da8acf"
- PHPStan/Larastan: "[OK] No errors"
- Migration exercise: selesai berdasarkan rekod pembangunan.
- A-15 Module Discovery Logging: "CLOSED / VERIFIED".
- GitBook DEV-094: diselaraskan dengan bukti CI dan rekod static analysis.

CI #64 mengesahkan langkah ujian automatik yang dijalankan pada commit yang dinyatakan. PHPStan/Larastan dijalankan secara berasingan dalam Codespace menggunakan konfigurasi "phpstan.neon" dengan had memori 512 MB.

Release Tag

"v0.3.1"

Release Commit

Rekodkan SHA commit sebenar yang ditetapkan sebagai tag "v0.3.1" selepas tag diwujudkan.

Release History

Versi| Skop| Status
v0.1.0| Core Basic Foundation| RELEASED
v0.2.0| Authentication, verification dan security hardening| RELEASED / VERIFIED
v0.3.0| Modular architecture readiness dan Core–Module boundaries| RELEASED / VERIFIED
v0.3.1| Architecture Audit & Design Lock| RELEASED / VERIFIED
v0.3.2| Core TASK Contract & Registry| PLANNED — NOT IMPLEMENTED
v0.4.0| Pick & Drop Business Workflow MVP| DEFERRED

Previous Release — v0.3.0

Release Tag: "v0.3.0"

Release Commit: "46d16edacf96395d3f233c8c63e2cd06c4ae4b3b"

Release Verification

- 191 ujian lulus
- 712 assertions
- 0 kegagalan
- MySQL 8.x
- PHP 8.3

Focused Test Verification

- 6 ujian pengesanan modul
- 9 assertions
- 0 kegagalan

v0.3.0 melengkapkan asas kesediaan Core dan sempadan Core–Module. Ia bukan pelepasan keseluruhan workflow Pick & Drop.

v0.3.1 — Architecture Audit & Design Lock

Skop pembangunan merangkumi:

- Audit source dan rekod keputusan berdasarkan bukti.
- Semakan integriti audit log dan lifecycle akaun.
- Semakan migration sequence dan rollback safety.
- Pengesahan ujian registration, authentication, profile, session, contact verification dan account lifecycle.
- Pengesahan Module Discovery dan sempadan Core–Module.
- Pengesahan pengendalian kegagalan penghantaran OTP melalui ujian.
- Pembetulan dan pengesahan isu registration dan Module Discovery.
- Static analysis PHP dan latihan migrasi.
- Penyelarasan README, GitBook dan bukti pengesahan.

Core Scope

- Account dan identiti akaun menggunakan UUID.
- Registration dan verification gate.
- Authentication dan session.
- Profile dan profile picture.
- Account lifecycle.
- Super Admin dan kawalan akaun istimewa.
- Audit Log.
- Security Foundation.
- Generic Module Discovery.
- Core–Module boundary.
- Rujukan akaun modul tanpa kebergantungan terus kepada model Account dalaman Core.

OTP Delivery

Penghantaran OTP sebenar ditangguhkan sehingga peringkat akhir production readiness.

Provider SMS/e-mel sebenar, konfigurasi production dan rahsia provider tidak termasuk dalam bukti CI semasa. Ujian kegagalan provider mengesahkan pengendalian kegagalan penghantaran dengan selamat; ia bukan bukti bahawa penghantaran OTP production telah tersedia.

Business Module — Pick & Drop

Pick & Drop ialah Business Module pertama yang dirancang.

Asas Pick & Drop yang telah dibangunkan bukan keseluruhan workflow operasi. Runner, pengagihan tugasan, bukti pickup/delivery dan aliran operasi lengkap tidak boleh dianggap siap hanya berdasarkan release Core.

Planned Core TASK Contract & Registry — v0.3.2

Skop yang dirancang:

- TASK registry generik dengan UUID.
- Kontrak atau adapter modul tanpa akses terus Core kepada jadual dalaman modul.
- Pendaftaran TASK idempotent berdasarkan "module_key", "task_type" dan "source_id".
- Task List generik dengan kawalan authorization.
- Status generik "OPEN", "ASSIGNED", "IN_PROGRESS", "COMPLETED" dan "CANCELLED".
- Primitive assignment selepas pengesahan eligibility dan policy modul.

v0.3.2 masih dirancang dan belum diimplementasikan atau dilepaskan.

Deferred Scope

Perkara berikut bukan sebahagian daripada release Core v0.3.1:

- Workflow operasi lengkap Pick & Drop.
- Runner dan tuntutan tugasan.
- Aplikasi Flutter.
- Aplikasi web.
- Enjin harga dan fi platform.
- Pembayaran dan penyelesaian kewangan.
- Pengagihan tugasan.
- Penghantaran OTP sebenar untuk production.

Pick & Drop Business Workflow MVP v0.4.0 kekal ditangguhkan sehingga kerja Core TASK Contract & Registry selesai dan skop seterusnya diluluskan.

Development Order

1. Backend / Core API.
2. Backend Testing & Review.
3. Core Readiness dan Core–Module Contract.
4. Architecture Audit & Design Lock.
5. Core TASK Contract & Registry.
6. Pick & Drop Business Workflow MVP.
7. Flutter User App.
8. Web App.

Tech Stack

Backend

- Laravel 13
- PHP 8.3
- MySQL 8.x

User App

- Flutter

Source of Truth

GitHub ialah Source of Truth untuk source code dan technical artifacts yang telah diterima.

GitBook digunakan untuk project knowledge, baseline, keputusan, requirements, architecture, audit records dan Development Activity Log.

GitHub dan GitBook hendaklah dikekalkan selari selepas setiap keputusan atau release rasmi.

Versioning and Release Control

- v0.3.0 ialah release terdahulu.
- v0.3.1 ialah release Architecture Audit & Design Lock.
- v0.3.2 masih "PLANNED — NOT IMPLEMENTED".
- v0.4.0 kekal "DEFERRED".
- Tag release sejarah tidak boleh dipindahkan atau ditulis semula.

Development Control

AUDIT → DESIGN LOCK → BATCH PLAN → IMPLEMENT → TEST → COMMIT → VERIFY → DOCUMENT → CLOSE
