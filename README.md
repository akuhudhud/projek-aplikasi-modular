
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
- GitBook DEV-094: diselaraskan berdasarkan rekod pengesahan terdahulu.

CI #64 mengesahkan ujian pada commit yang dinyatakan. Ia tidak
membuktikan bahawa fungsi Marketplace telah dibangunkan.

### Release Tag

`v0.3.1`

Tag dan sejarah release terdahulu mesti dikekalkan. Jangan
memindahkan atau menulis semula tag release.

## Major Change — v0.4.0 Direct Runner Marketplace

**Status: APPROVED DIRECTION / IMPLEMENTATION BASELINE IN PREPARATION**

v0.4.0 ialah baseline pembangunan baharu untuk Major Change
GIGAVERSE berdasarkan hala tuju Direct Runner Marketplace.

Skop lama v0.4.0 yang berfokus pada Pick & Drop Business
Workflow MVP telah dibatalkan dan digantikan oleh hala tuju ini.

### Product Model

1. Pelanggan membuat permintaan servis.
2. Runner yang diluluskan dan layak boleh memberikan sebut harga.
3. Pelanggan memilih satu sebut harga.
4. Sistem memastikan hanya satu sebut harga berjaya dipilih
   bagi setiap permintaan.
5. Runner terpilih ditugaskan melalui kontrak Core yang sah.

### Public Runner Profile

- Runner yang diluluskan dan layak boleh mempunyai profil awam.
- Setiap profil mempunyai URL unik yang boleh dikongsi.
- Pelanggan boleh memulakan permintaan khusus kepada runner itu.
- Permintaan melalui profil mesti menggunakan aliran sebut harga
  Marketplace yang sama.
- Pautan profil tidak boleh menetapkan tugasan secara automatik
  atau memintas kebenaran dan kelayakan runner.
- Maklumat peribadi yang tidak diluluskan untuk paparan awam
  mesti kekal dilindungi.

### Target Component Ownership

- **Core:** Identiti TASK generik, lifecycle dan penugasan.
- **Marketplace Engine:** Permintaan, sebut harga, pemilihan,
  penutupan sebut harga bersaing dan atribusi sumber permintaan.
- **Business Modules:** Data dan peraturan khusus bagi setiap servis.
- **Identity / SIDCORE:** Mengikut kontrak integrasi yang perlu
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

Senarai ini ialah arah penerimaan. Ia bukan bukti bahawa
pelaksanaan sudah lengkap.

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

Pembatalan skop pembangunan tidak memadam kod, migration,
commit atau tag sejarah secara automatik. Komponen lama mesti
diaudit untuk menentukan sama ada ia dikekalkan, digunakan
semula, diganti atau dipadam.

## Repository Audit Findings

Pada HEAD `d09a9bb6447d8bdcbe19fc67fc2ef48de6427358`, audit awal
mengesahkan kewujudan:

- Account, session, authentication dan security foundation.
- Runner application dan status approval.
- Core TASK registry, assignment dan lifecycle.
- Modul Pick & Drop dengan request dan task dalaman.
- Ujian untuk Core TASK, runner, authentication dan Pick & Drop.

Audit awal belum menemui pelaksanaan Marketplace Engine,
quotation workflow, public runner profile atau frontend Flutter/Web
dalam struktur repositori yang diperiksa.

Penemuan ini perlu disahkan semula apabila audit dan pelaksanaan
berikutnya dijalankan pada HEAD terkini.

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
keadaan repositori yang diaudit.

## Source of Truth

- GitHub: source code, migration, ujian dan technical artifacts
  yang telah diterima.
- GitBook: project knowledge, keputusan, requirements, architecture,
  audit records dan Development Activity Log.

GitHub dan GitBook perlu diselaraskan selepas keputusan atau
release rasmi. Jangan mendakwa penyelarasan selesai tanpa bukti.

## Development Control

AUDIT → DESIGN LOCK → BASELINE APPROVAL → BATCH PLAN
→ IMPLEMENT → TEST → COMMIT → VERIFY → DOCUMENT → CLOSE

### Immediate Next Steps

1. Selesaikan audit kebergantungan dan regression tests.
2. Sahkan kontrak Core TASK yang diperlukan Marketplace.
3. Audit sempadan identiti dan SIDCORE.
4. Kunci reka bentuk terperinci Marketplace Engine.
5. Luluskan pelan batch dan kriteria penerimaan.
6. Mulakan implementasi hanya selepas baseline diluluskan.

Jangan padam modul atau migration sebelum kebergantungan,
kesan terhadap ujian dan strategi penggantian disahkan.
