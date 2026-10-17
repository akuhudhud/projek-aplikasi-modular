
# Projek Aplikasi Modular — GIGAVERSE

GIGAVERSE ialah platform aplikasi modular dengan Core bersama,
Marketplace Engine dan Business Modules yang boleh dikembangkan
secara berasingan.

## Current Verified Release

**v0.3.1 — Architecture Audit & Design Lock**

Status: RELEASED / VERIFIED

Release ini memfokuskan pengesahan seni bina Core, sempadan
Core–Module, audit teknikal, keselamatan dan kebolehpercayaan
backend.

### Release Verification

- CI Run: [#64 — success](https://github.com/akuhudhud/projek-aplikasi-modular/actions/runs/38568276517)
- Verified CI commit: `53629b0380a1757a141968409713db7f05da8acf`
- PHPStan/Larastan: `[OK] No errors` berdasarkan rekod pembangunan.
- A-15 Module Discovery Logging: `CLOSED / VERIFIED`.
- GitBook DEV-094: direkodkan sebagai penyelarasan terdahulu.

CI #64 mengesahkan ujian pada commit yang dinyatakan. Ia bukan
bukti bahawa fungsi Marketplace telah dibangunkan.

### Release Tag

`v0.3.1`

Tag release dan sejarah commit mesti dikekalkan. Jangan memindahkan
atau menulis semula tag release.

## Major Change — v0.4.0 Direct Runner Marketplace

**Status: APPROVED DIRECTION / IMPLEMENTATION BASELINE IN PREPARATION**

v0.4.0 ialah baseline pembangunan baharu GIGAVERSE berdasarkan
hala tuju Direct Runner Marketplace.

Skop lama v0.3.2 dibatalkan. Skop lama v0.4.0 yang berfokus pada
Pick & Drop Business Workflow MVP juga dibatalkan dan tidak boleh
dianggap sebagai skop aktif.

### Product Model

1. Pelanggan membuat permintaan servis.
2. Hanya runner yang diluluskan dan layak boleh menawarkan servis.
3. Runner memberikan sebut harga terhadap permintaan.
4. Pelanggan memilih satu sebut harga.
5. Sistem memastikan hanya satu sebut harga menang bagi setiap
   permintaan.
6. Runner terpilih ditugaskan melalui kontrak Core yang sah.

Model harga yang dipersetujui ialah pelanggan membuat permintaan
dan runner memberikan sebut harga. Harga tetap atau enjin harga
tidak boleh dianggap sebahagian daripada skop tanpa keputusan
reka bentuk yang diluluskan.

### Public Runner Profile

- Runner yang diluluskan dan layak boleh mempunyai profil awam.
- Setiap profil mempunyai URL unik yang boleh dikongsi.
- Pelanggan boleh memulakan permintaan khusus kepada runner itu.
- Permintaan melalui profil menggunakan aliran sebut harga
  Marketplace yang sama.
- Pautan profil tidak boleh menetapkan tugasan secara automatik
  atau memintas kebenaran dan kelayakan runner.
- Maklumat peribadi yang tidak diluluskan untuk paparan awam
  mesti kekal dilindungi.
- Jika runner tidak lagi layak atau tidak tersedia untuk permintaan
  baharu, sistem mesti mengendalikan permintaan dengan selamat.

### Target Component Ownership

- **Core:** Identiti TASK generik, lifecycle dan penugasan.
- **Marketplace Engine:** Permintaan, sebut harga, pemilihan,
  penutupan sebut harga bersaing dan atribusi sumber permintaan.
- **Business Modules:** Data dan peraturan khusus bagi setiap servis.
- **Identity / SIDCORE:** Integrasi mengikut kontrak yang mesti
  diaudit dan diluluskan sebelum pelaksanaan.

Marketplace tidak boleh bergantung sepenuhnya pada Pick & Drop.
Pick & Drop boleh menjadi Business Module awal selepas kontrak
integrasi disahkan.

### v0.4.0 Acceptance Direction

- Permintaan servis boleh dicipta dan dijejak.
- Runner yang layak boleh memberikan sebut harga.
- Pelanggan boleh memilih hanya satu sebut harga yang sah.
- Penerimaan sebut harga dan penugasan TASK selamat terhadap
  operasi serentak dan permintaan berulang.
- Permintaan daripada marketplace dan pautan profil menggunakan
  peraturan pemilihan yang sama.
- Core kekal pemilik lifecycle TASK generik.
- Data khusus servis kekal di bawah Business Module.
- Ujian automatik, static analysis dan CI yang berkaitan lulus.
- Dokumentasi sepadan dengan kod yang benar-benar disahkan.

Senarai ini ialah arah penerimaan, bukan bukti pelaksanaan selesai.

## Version and Scope Decisions

| Versi | Keputusan |
|---|---|
| v0.1.0 | RELEASED |
| v0.2.0 | RELEASED / VERIFIED |
| v0.3.0 | RELEASED / VERIFIED |
| v0.3.1 | RELEASED / VERIFIED |
| v0.3.2 | SKOP LAMA DIBATALKAN — BUKAN RELEASE |
| v0.4.0 lama | Skop Pick & Drop MVP DIBATALKAN |
| v0.4.0 baharu | Direct Runner Marketplace — baseline baharu |

Pembatalan skop tidak memadam kod, migration, commit atau tag
sejarah secara automatik. Komponen lama mesti diaudit dan
diklasifikasikan sebagai KEEP, REUSE, REPLACE atau DELETE.
Jangan padam komponen sebelum kebergantungan dan kesan regresi
disahkan.

## Release History and Core Foundation

### v0.3.0 — Modular Architecture Readiness

Rekod terdahulu menyatakan release ini melengkapkan asas kesediaan
Core dan sempadan Core–Module. Ia bukan pelepasan keseluruhan
workflow operasi Pick & Drop.

Rekod pengesahan terdahulu:
- Release tag: `v0.3.0`
- Release commit: `46d16edacf96395d3f233c8c63e2cd06c4ae4b3b`
- 191 ujian lulus, 712 assertions dan 0 kegagalan.
- Ujian pengesanan modul: 6 ujian, 9 assertions dan 0 kegagalan.
- Persekitaran pengesahan yang direkodkan: MySQL 8.x dan PHP 8.3.

Maklumat ini ialah rekod sejarah dan tidak menggantikan pengesahan
pada HEAD semasa.

### Core Foundation

Asas Core yang telah dibangunkan merangkumi:

- Account dan identiti akaun menggunakan UUID.
- Registration dan verification gate.
- Authentication dan session.
- Profile dan profile picture.
- Account lifecycle.
- Super Admin dan kawalan akaun istimewa.
- Audit Log dan Security Foundation.
- Generic Module Discovery.
- Core–Module boundary.
- Rujukan akaun modul tanpa kebergantungan terus kepada model
  Account dalaman Core.
- Core TASK registry, assignment dan lifecycle.

Komponen ini hendaklah dikekalkan jika masih memenuhi keperluan
baharu. Perubahan hanya dibuat apabila audit menunjukkan jurang
kontrak atau keperluan yang benar-benar memerlukannya.

### OTP Delivery

Penghantaran OTP sebenar ditangguhkan sehingga peringkat akhir
production readiness.

Provider SMS/e-mel sebenar, konfigurasi production dan rahsia
provider bukan sebahagian daripada bukti CI terdahulu. Ujian
pengendalian kegagalan provider bukan bukti bahawa penghantaran
OTP production telah tersedia.

## Repository Audit Findings

Audit terdahulu pada HEAD
`d09a9bb6447d8bdcbe19fc67fc2ef48de6427358` mengesahkan kewujudan:

- Account, session, authentication dan security foundation.
- Runner application dan status approval.
- Core TASK registry, assignment dan lifecycle.
- Modul Pick & Drop dengan request dan task dalaman.
- Ujian untuk Core TASK, runner, authentication dan Pick & Drop.

Audit tersebut belum menemui pelaksanaan Marketplace Engine,
quotation workflow, public runner profile atau frontend Flutter/Web
dalam struktur repositori yang diperiksa.

Penemuan mesti disahkan semula pada HEAD terkini sebelum sebarang
komponen diklasifikasikan untuk diganti atau dipadam.

## Technology Stack

### Backend

- Laravel
- PHP `^8.3`
- MySQL 8.x
- PHPUnit
- Larastan / PHPStan

Database production dan pengesahan migrasi hendaklah menggunakan
MySQL yang disokong. Jangan menulis semula sejarah migration;
gunakan migration baharu untuk perubahan skema.

### Client Applications

Flutter User App dan aplikasi web belum dianggap siap berdasarkan
keadaan repositori yang diaudit. Jangan anggap frontend tersedia
sehingga kewujudannya disahkan.

## Source of Truth

- **GitHub:** source code, migration, ujian dan technical artifacts
  yang telah diterima.
- **GitBook:** project knowledge, keputusan, requirements,
  architecture, audit records dan Development Activity Log.

GitHub dan GitBook perlu diselaraskan selepas keputusan atau release
rasmi. Jangan mendakwa penyelarasan selesai tanpa bukti.

## Development Control

AUDIT → DESIGN LOCK → BASELINE APPROVAL → BATCH PLAN
→ IMPLEMENT → TEST → COMMIT → VERIFY → DOCUMENT → CLOSE

### Immediate Next Steps

1. Pulihkan rekod sejarah README ini.
2. Sahkan CI dan HEAD selepas perubahan dokumentasi.
3. Selaraskan `docs/architecture/target-architecture.md` dengan
   keputusan bahawa v0.3.2 dan skop lama v0.4.0 dibatalkan.
4. Lengkapkan audit kebergantungan Core TASK dan Business Modules.
5. Audit sempadan identiti dan SIDCORE.
6. Kunci kontrak Marketplace Engine dan pelan batch.
7. Mulakan implementasi hanya selepas baseline reka bentuk
   dan kriteria penerimaan diluluskan.

Jangan padam modul atau migration sebelum kebergantungan, kesan
terhadap ujian dan strategi penggantian disahkan.
