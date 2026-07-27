#!/bin/bash
# Membuat dua role database Rafin. Dijalankan sekali, saat volume postgres
# masih kosong.
#
# Pemisahan ini adalah setengah dari aturan A4. Lapisan pertama — global
# scope Eloquent — hidup di dalam kode aplikasi dan karena itu bisa dilewati
# oleh satu query mentah yang lupa. Lapisan kedua adalah Row Level Security,
# dan RLS hanya berarti kalau role yang dipakai aplikasi memang tidak bisa
# melewatinya. Role yang memiliki tabelnya sendiri melewati policy-nya
# sendiri secara diam-diam, itulah sebabnya keduanya harus terpisah.
#
#   rafin_owner  pemilik skema. BYPASSRLS. Hanya dipakai migration.
#   rafin_app    dipakai aplikasi. NOBYPASSRLS, tanpa hak membuat tabel.

set -euo pipefail

: "${RAFIN_APP_PASSWORD:?RAFIN_APP_PASSWORD wajib diisi}"
: "${RAFIN_OWNER_PASSWORD:?RAFIN_OWNER_PASSWORD wajib diisi}"

psql -v ON_ERROR_STOP=1 \
     --username "$POSTGRES_USER" \
     --dbname "$POSTGRES_DB" \
     --set app_password="$RAFIN_APP_PASSWORD" \
     --set owner_password="$RAFIN_OWNER_PASSWORD" <<-'SQL'

	-- Pemilik skema. BYPASSRLS supaya migration bisa menyentuh tabel yang
	-- sudah dipagari policy. Bukan SUPERUSER: kalau kredensial ini bocor,
	-- yang didapat adalah satu database, bukan seluruh instance.
	CREATE ROLE rafin_owner
	    LOGIN
	    PASSWORD :'owner_password'
	    BYPASSRLS
	    NOSUPERUSER
	    NOCREATEROLE
	    NOCREATEDB;

	-- Role aplikasi. Segala yang tidak diberikan di sini memang sengaja
	-- tidak diberikan.
	CREATE ROLE rafin_app
	    LOGIN
	    PASSWORD :'app_password'
	    NOBYPASSRLS
	    NOSUPERUSER
	    NOCREATEROLE
	    NOCREATEDB
	    NOINHERIT;

	-- Skema public berpindah tangan ke rafin_owner supaya migration bisa
	-- membuat tabel tanpa perlu hak superuser.
	ALTER SCHEMA public OWNER TO rafin_owner;

	-- Tidak seorang pun boleh membuat objek di public kecuali pemiliknya.
	-- PostgreSQL 15 ke atas sudah begini secara bawaan; dinyatakan ulang
	-- karena hak akses lebih baik terbaca daripada diingat.
	REVOKE CREATE ON SCHEMA public FROM PUBLIC;
	REVOKE ALL    ON SCHEMA public FROM rafin_app;
	GRANT  USAGE  ON SCHEMA public TO   rafin_app;

	-- Inilah yang membuat aplikasi bisa membaca tabelnya sendiri.
	--
	-- Tabel bertenant memang menerima GRANT eksplisit dari Rls::enableFor()
	-- saat migration. Tapi tabel yang tidak bertenant — users, sessions,
	-- cache, jobs — tidak, dan tanpa baris di bawah ini rafin_app tidak
	-- bisa membaca satu pun dari mereka. Gejalanya muncul jauh dari
	-- sebabnya: aplikasi menyala normal lalu gagal di query pertama.
	--
	-- Cakupannya sengaja DML saja. Tidak ada TRUNCATE, tidak ada REFERENCES,
	-- tidak ada satu pun bentuk DDL.
	ALTER DEFAULT PRIVILEGES FOR ROLE rafin_owner IN SCHEMA public
	    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO rafin_app;

	ALTER DEFAULT PRIVILEGES FOR ROLE rafin_owner IN SCHEMA public
	    GRANT USAGE, SELECT ON SEQUENCES TO rafin_app;

	ALTER DEFAULT PRIVILEGES FOR ROLE rafin_owner IN SCHEMA public
	    GRANT EXECUTE ON FUNCTIONS TO rafin_app;

	-- Database ini hanya untuk Rafin. Tidak ada yang perlu menyambung ke
	-- sini selain kedua role di atas.
	REVOKE CONNECT ON DATABASE :"POSTGRES_DB" FROM PUBLIC;
	GRANT  CONNECT ON DATABASE :"POSTGRES_DB" TO rafin_owner, rafin_app;

SQL

echo "Role rafin_owner dan rafin_app dibuat."
