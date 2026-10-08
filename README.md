# Projek Aplikasi Modular

Platform aplikasi modular dengan teras bersama dan modul perniagaan yang boleh dipasang, dikembangkan dan diurus secara berasingan.

## Current Release

**v0.2.0 — Core Security & Authentication**

**Status: RELEASED / VERIFIED**

Core v0.2.0 telah melengkapkan current Core authentication, account lifecycle, verification, session, profile, audit dan security hardening scope.

### Release Verification

- 171 tests passed
- 661 assertions
- 0 failures
- MySQL 8.0
- PHP 8.3

### Release Tag

`v0.2.0`

### Release Commit

`c4cc4af6acf73b5e68495954d51efa3a34e37e03`

## Core Scope

- Account
- Registration
- Authentication
- Contact Verification
- OTP
- Profile
- Profile Picture
- Contact Change
- Session
- Account Lifecycle
- Super Admin
- Audit Log
- Security Foundation

## Current Development State

Core v0.2.0 telah RELEASED.

Pembangunan seterusnya tidak terus masuk Business Module.

Next gate:

**Core Readiness Design Lock → Core ↔ Business Module Contract**

Selepas contract dikunci, Pick & Drop akan menjadi Business Module pertama yang dibangunkan.

## Business Module

Business Module belum diimplementasikan.

Pick & Drop ialah Business Module pertama yang dirancang.

## Development Order

1. Backend / Core API
2. Backend Testing & Review
3. Core Readiness Design Lock
4. Core ↔ Business Module Contract
5. Business Module implementation
6. Flutter User App
7. Web App

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

## Development Control

**AUDIT → DESIGN LOCK → BATCH PLAN → IMPLEMENT → TEST → COMMIT → VERIFY → DOCUMENT → CLOSE**
