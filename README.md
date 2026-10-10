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

## Current Development State

Core v0.2.0 telah RELEASED / VERIFIED.

Core v0.3.0 telah RELEASED / VERIFIED.

Penerimaan kesediaan Core v0.3 telah LULUS, DITUTUP dan DISAHKAN berdasarkan kriteria penerimaan serta bukti kod dan pengujian yang direkodkan dalam GitBook.

Pick & Drop ialah Business Module pertama yang dirancang.

Kerja asas Pick & Drop yang telah disiapkan adalah terhad kepada skop yang telah diluluskan. Keseluruhan aliran operasi Pick & Drop masih belum siap.

### Deferred Scope

Perkara berikut belum termasuk dalam release Core v0.3.0:

- Aliran operasi lengkap Pick & Drop
- Runner dan tuntutan tugasan
- Aplikasi Flutter
- Aplikasi web
- Enjin harga dan fi platform
- Pembayaran dan penyelesaian kewangan
- Pengagihan tugasan
- Penghantaran OTP sebenar untuk persekitaran produksi

Penghantaran OTP sebenar kekal sebagai gerbang kesediaan produksi yang berasingan.

Semakan arah kebergantungan yang lulus adalah berdasarkan corak dan bahagian kod yang diperiksa. Semakan ini bukan analisis statik PHP menyeluruh.

## Business Module

Business Module dibangunkan secara berasingan daripada Core dengan mematuhi kontrak dan sempadan integrasi yang diluluskan.

Pick & Drop ialah Business Module pertama yang dirancang.

Pembangunan modul seterusnya mesti mengikut skop dan reka bentuk yang diluluskan. Jangan menganggap release Core v0.3.0 sebagai bukti bahawa keseluruhan modul Pick & Drop telah siap.

## Development Order

1. Backend / Core API
2. Backend Testing & Review
3. Core Readiness dan Core–Module Contract
4. Pick & Drop Module Foundation
5. Flutter User App
6. Web App

Urutan pelaksanaan terperinci tertakluk kepada baseline, keputusan dan rekod pembangunan terkini dalam GitBook.

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

## Development Control

**AUDIT → DESIGN LOCK → BATCH PLAN → IMPLEMENT → TEST → COMMIT → VERIFY → DOCUMENT → CLOSE**
