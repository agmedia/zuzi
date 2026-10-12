-- ZUZI.hr: najava Interlibera 2026.
-- 1. Prvo postaviti obje slike iz public/media/img/blog/interliber-2026/.
-- 2. Odabrati ZUZI bazu i pokrenuti ovaj cijeli SQL.
-- Postavlja naslov, HTML, glavnu sliku, sazetak, SEO i status objave.
-- Sigurno za ponavljanje: azurira istu objavu po slugu; ne brise podatke.
-- Kod ponavljanja cuva created_at, publish_date i viewed.
-- Ako isti slug vec postoji vise puta ili lock nije dostupan, ne pise nista.
-- Datum objave je trenutak prvog pokretanja u vremenskoj zoni baze.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @blog_slug := 'zuzihr-na-interliberu-2026-vidimo-se-u-paviljonima-6-i-7';
SET @blog_title := 'ZUZI.hr na Interliberu 2026.: vidimo se u paviljonima 6 i 7!';
SET @blog_short_description := 'Vidimo se na Interliberu od 10. do 15. studenoga 2026.! ZUZI.hr vas čeka u paviljonu 6 na štandu 3A i u paviljonu 7 na štandu 31.';
SET @blog_meta_title := 'ZUZI.hr na Interliberu 2026. | Paviljoni 6 i 7';
SET @blog_meta_description := 'Posjetite ZUZI.hr na Interliberu od 10. do 15. studenoga 2026.: paviljon 6, štand 3A i paviljon 7, štand 31. Ulaz je besplatan!';
SET @blog_keywords := 'ZUZI.hr, Interliber 2026, sajam knjiga, Zagrebački velesajam, paviljon 6, štand 3A, paviljon 7, štand 31';
SET @blog_image := 'media/img/blog/interliber-2026/zuzi-interliber-2026.jpg';
SET @published_at := NOW();
SET @blog_description := CONVERT(FROM_BASE64(CONCAT(
    'PHA+PHN0cm9uZz5aVVpJLmhyIHN0acW+ZSBuYSBJbnRlcmxpYmVyIDIwMjYuITwvc3Ryb25nPiBPZCA8c3Ryb25nPjEwLiBkbyAxNS4gc3R1ZGVub2dhIDIw',
    'MjYuPC9zdHJvbmc+IGRydcW+aW1vIHNlIG5hIFphZ3JlYmHEjWtvbSB2ZWxlc2FqbXUsIGdkamUgc2Ugb2Ryxb5hdmEgNDguIG1lxJF1bmFyb2RuaSBzYWph',
    'bSBrbmppZ2EuIE92ZSBnb2RpbmUgbW/FvmV0ZSBuYXMgcHJvbmHEh2kgbmEgZHZpamUgbG9rYWNpamU6IHUgPHN0cm9uZz5wYXZpbGpvbnUgNiwgbmEgxaF0',
    'YW5kdSAzQTwvc3Ryb25nPiwgaSB1IDxzdHJvbmc+cGF2aWxqb251IDcsIG5hIMWhdGFuZHUgMzE8L3N0cm9uZz4uPC9wPgoKPHA+TmFrb24gPGEgaHJlZj0i',
    'L2Jsb2cvaHZhbGEtdmFtLXNwbGl0ZS16dXppaHItbmEtbWVkaXRlcmFuc2tvbS1mZXN0aXZhbHUta25qaWdlLTIwMjYiPnN1c3JldGEgbmEgTWVkaXRlcmFu',
    'c2tvbSBmZXN0aXZhbHUga25qaWdlIHUgU3BsaXR1PC9hPiwgdmVzZWxpbW8gc2Ugbm92b20gZHJ1xb5lbmp1IHMgxI1pdGF0ZWxqaW1hLiBJbnRlcmxpYmVy',
    'IGplIHByaWxpa2EgZGEgc2UgdXBvem5hbW8gdcW+aXZvLCByYXptaWplbmltbyBwcmVwb3J1a2UgaSB6YWplZG5vIHBvdHJhxb5pbW8ga25qaWd1IGtvanUg',
    'xIdldGUgcmFkbyBwb25pamV0aSBrdcSHaS48L3A+Cgo8aDI+UG90cmHFvml0ZSBaVVpJIHUgcGF2aWxqb25pbWEgNiBpIDc8L2gyPgoKPGRpdiBjbGFzcz0i',
    'cm93IiBzdHlsZT0ibWFyZ2luLXRvcDogMXJlbTsiPgogICAgPGRpdiBjbGFzcz0iY29sLTEyIGNvbC1tZC02Ij4KICAgICAgICA8ZGl2IHN0eWxlPSJwYWRk',
    'aW5nOiAxLjI1cmVtOyBtYXJnaW4tYm90dG9tOiAxcmVtOyBiYWNrZ3JvdW5kOiAjZjZmOWZjOyBib3JkZXItbGVmdDogNHB4IHNvbGlkICMwMDY5NWY7IGJv',
    'cmRlci1yYWRpdXM6IC41cmVtOyI+CiAgICAgICAgICAgIDxwIHN0eWxlPSJtYXJnaW46IDAgMCAuMjVyZW07Ij5QYXZpbGpvbiA8c3Ryb25nPjY8L3N0cm9u',
    'Zz48L3A+CiAgICAgICAgICAgIDxwIHN0eWxlPSJtYXJnaW46IDA7IGZvbnQtc2l6ZTogMS40cmVtOyI+PHN0cm9uZz7FoHRhbmQgM0E8L3N0cm9uZz48L3A+',
    'CiAgICAgICAgPC9kaXY+CiAgICA8L2Rpdj4KICAgIDxkaXYgY2xhc3M9ImNvbC0xMiBjb2wtbWQtNiI+CiAgICAgICAgPGRpdiBzdHlsZT0icGFkZGluZzog',
    'MS4yNXJlbTsgbWFyZ2luLWJvdHRvbTogMXJlbTsgYmFja2dyb3VuZDogI2Y2ZjlmYzsgYm9yZGVyLWxlZnQ6IDRweCBzb2xpZCAjMDA2OTVmOyBib3JkZXIt',
    'cmFkaXVzOiAuNXJlbTsiPgogICAgICAgICAgICA8cCBzdHlsZT0ibWFyZ2luOiAwIDAgLjI1cmVtOyI+UGF2aWxqb24gPHN0cm9uZz43PC9zdHJvbmc+PC9w',
    'PgogICAgICAgICAgICA8cCBzdHlsZT0ibWFyZ2luOiAwOyBmb250LXNpemU6IDEuNHJlbTsiPjxzdHJvbmc+xaB0YW5kIDMxPC9zdHJvbmc+PC9wPgogICAg',
    'ICAgIDwvZGl2PgogICAgPC9kaXY+CjwvZGl2PgoKPHA+RG/EkWl0ZSByYXpnbGVkYXRpIHBvbnVkdSBuYcWhZSBrbmppxb5hcmUgaSBhbnRpa3ZhcmlqYXRh',
    'LCBwcmVsaXN0YXRpIGtuamlnZSBpIHBvcmF6Z292YXJhdGkgcyBuYW1hLiBCaWxvIGRhIHRyYcW+aXRlIG5lxaF0byB6YSB2bGFzdGl0dSBwb2xpY3UsIHBv',
    'a2xvbiB6YSBkcmFndSBvc29idSBpbGkgaWRlanUgemEgc2xqZWRlxIdlIMSNaXRhbmplLCByYWRvIMSHZW1vIHZhbSBwb21vxIdpIHUgb2RhYmlydS48L3A+',
    'Cgo8cD5Qb25lc2l0ZSBwb3BpcyBuYXNsb3ZhIGtvamUgdmXEhyBkdWdvIMW+ZWxpdGUgcHJvxI1pdGF0aSwgYWxpIG9zdGF2aXRlIG1qZXN0YSBpIHphIHBv',
    'bmVrbyBzbHXEjWFqbm8gb3RrcmnEh2UuIFVwcmF2byBzdSB0YWt2aSBzdXNyZXRpIHMga25qaWdhbWEgamVkYW4gb2QgcmF6bG9nYSB6Ym9nIGtvamloIHZv',
    'bGltbyBzYWptb3ZlOiBkb8SRZXRlIHMgamVkbm9tIGlkZWpvbSwgYSBrdcSHaSBzZSB2cmF0aXRlIHMgbm92b20gcHJpxI1vbS48L3A+Cgo8aDI+SXNwbGFu',
    'aXJhanRlIHBvc2pldCBJbnRlcmxpYmVydTwvaDI+Cgo8cD5TYWphbSBzZSBvZHLFvmF2YSBuYSA8c3Ryb25nPlphZ3JlYmHEjWtvbSB2ZWxlc2FqbXUsIEF2',
    'ZW5pamEgRHVicm92bmlrIDE1LCBaYWdyZWI8L3N0cm9uZz4uIDxzdHJvbmc+VWxheiBqZSBiZXNwbGF0YW4gemEgc3ZlIHBvc2pldGl0ZWxqZTwvc3Ryb25n',
    'PiwgcGEgcG96b3ZpdGUgcHJpamF0ZWxqZSBpIG9iaXRlbGogbmEgemFqZWRuacSNa2kgb2JpbGF6YWsuIFBvZGF0a2UgbyB1bGF6dSBpIHBvc2pldHUgbW/F',
    'vmV0ZSBwcm92amVyaXRpIG5hIDxhIGhyZWY9Imh0dHBzOi8vd3d3Lnp2LmhyL3Nham1vdmktNy9pbnRlcmxpYmVyLTI1MTkvcG9zamV0aXRlbGppLTI3MDcv',
    'MjcwNyI+c2x1xb5iZW5vaiBzdHJhbmljaSBJbnRlcmxpYmVyYTwvYT4uPC9wPgoKPGRpdiBzdHlsZT0ib3ZlcmZsb3cteDogYXV0bzsgbWFyZ2luOiAxLjI1',
    'cmVtIDA7Ij4KICAgIDx0YWJsZSBzdHlsZT0id2lkdGg6IDEwMCU7IGJvcmRlci1jb2xsYXBzZTogY29sbGFwc2U7IHRleHQtYWxpZ246IGxlZnQ7Ij4KICAg',
    'ICAgICA8Y2FwdGlvbiBzdHlsZT0iY2FwdGlvbi1zaWRlOiB0b3A7IHRleHQtYWxpZ246IGxlZnQ7IGNvbG9yOiBpbmhlcml0OyBwYWRkaW5nOiAwIDAgLjc1',
    'cmVtOyI+PHN0cm9uZz5SYWRubyB2cmlqZW1lIEludGVybGliZXJhIDIwMjYuPC9zdHJvbmc+PC9jYXB0aW9uPgogICAgICAgIDx0aGVhZD4KICAgICAgICAg',
    'ICAgPHRyPgogICAgICAgICAgICAgICAgPHRoIHNjb3BlPSJjb2wiIHN0eWxlPSJwYWRkaW5nOiAuNzVyZW07IGJvcmRlci1ib3R0b206IDJweCBzb2xpZCAj',
    'ZGZlNWViOyI+RGF0dW08L3RoPgogICAgICAgICAgICAgICAgPHRoIHNjb3BlPSJjb2wiIHN0eWxlPSJwYWRkaW5nOiAuNzVyZW07IGJvcmRlci1ib3R0b206',
    'IDJweCBzb2xpZCAjZGZlNWViOyI+UmFkbm8gdnJpamVtZTwvdGg+CiAgICAgICAgICAgIDwvdHI+CiAgICAgICAgPC90aGVhZD4KICAgICAgICA8dGJvZHk+',
    'CiAgICAgICAgICAgIDx0cj4KICAgICAgICAgICAgICAgIDx0ZCBzdHlsZT0icGFkZGluZzogLjc1cmVtOyBib3JkZXItYm90dG9tOiAxcHggc29saWQgI2Rm',
    'ZTVlYjsiPjEwLiDigJMgMTIuIHN0dWRlbm9nYSAodXRvcmFrIOKAkyDEjWV0dnJ0YWspPC90ZD4KICAgICAgICAgICAgICAgIDx0ZCBzdHlsZT0icGFkZGlu',
    'ZzogLjc1cmVtOyBib3JkZXItYm90dG9tOiAxcHggc29saWQgI2RmZTVlYjsiPjEwOjAwIOKAkyAyMDowMDwvdGQ+CiAgICAgICAgICAgIDwvdHI+CiAgICAg',
    'ICAgICAgIDx0cj4KICAgICAgICAgICAgICAgIDx0ZCBzdHlsZT0icGFkZGluZzogLjc1cmVtOyBib3JkZXItYm90dG9tOiAxcHggc29saWQgI2RmZTVlYjsi',
    'PjEzLiBpIDE0LiBzdHVkZW5vZ2EgKHBldGFrIGkgc3Vib3RhKTwvdGQ+CiAgICAgICAgICAgICAgICA8dGQgc3R5bGU9InBhZGRpbmc6IC43NXJlbTsgYm9y',
    'ZGVyLWJvdHRvbTogMXB4IHNvbGlkICNkZmU1ZWI7Ij4xMDowMCDigJMgMjE6MDA8L3RkPgogICAgICAgICAgICA8L3RyPgogICAgICAgICAgICA8dHI+CiAg',
    'ICAgICAgICAgICAgICA8dGQgc3R5bGU9InBhZGRpbmc6IC43NXJlbTsgYm9yZGVyLWJvdHRvbTogMXB4IHNvbGlkICNkZmU1ZWI7Ij4xNS4gc3R1ZGVub2dh',
    'IChuZWRqZWxqYSk8L3RkPgogICAgICAgICAgICAgICAgPHRkIHN0eWxlPSJwYWRkaW5nOiAuNzVyZW07IGJvcmRlci1ib3R0b206IDFweCBzb2xpZCAjZGZl',
    'NWViOyI+MDk6MDAg4oCTIDE5OjAwPC90ZD4KICAgICAgICAgICAgPC90cj4KICAgICAgICA8L3Rib2R5PgogICAgPC90YWJsZT4KPC9kaXY+Cgo8cD5aYSBh',
    'a3R1YWxuZSBpbmZvcm1hY2lqZSBwcmlqZSBkb2xhc2thIHBvZ2xlZGFqdGUgPGEgaHJlZj0iaHR0cHM6Ly93d3cuenYuaHIvc2FqbW92aS03L2ludGVybGli',
    'ZXItMjUxOS9wb3NqZXRpdGVsamktMjcwNy9yYWRuby12cmlqZW1lLWktcGxhbi1zYWptYS84OTgyIj5zbHXFvmJlbm8gcmFkbm8gdnJpamVtZSBpIHBsYW4g',
    'c2FqbWE8L2E+LjwvcD4KCjxoMj5WaWRpbW8gc2UgbWXEkXUga25qaWdhbWEhPC9oMj4KCjxwPkFrbyBuYXMgdmXEhyBwcmF0aXRlIG5hIDxhIGhyZWY9Imh0',
    'dHBzOi8vd3d3Lnp1emkuaHIvIj5aVVpJLmhyPC9hPiwgYml0IMSHZSBuYW0gZHJhZ28gc3VzcmVzdGkgdmFzIGkgdcW+aXZvLiBBa28gbmFzIHRlayB1cG96',
    'bmFqZXRlLCBzdnJhdGl0ZSBkbyBuYcWhaWggxaF0YW5kb3ZhIGkgemF2aXJpdGUgdSBwb251ZHUuIFphIHJhemdvdm9yIG8gZG9icm9qIGtuaml6aSB1dmlq',
    'ZWsgc21vIHJhc3BvbG/FvmVuaS48L3A+Cgo8cD48c3Ryb25nPlphYmlsamXFvml0ZSBkYXR1bWU6IDEwLiDigJMgMTUuIHN0dWRlbm9nYSAyMDI2Ljxicj5a',
    'VVpJLmhyIHZhcyDEjWVrYSB1IHBhdmlsam9udSA2IG5hIMWhdGFuZHUgM0EgaSB1IHBhdmlsam9udSA3IG5hIMWhdGFuZHUgMzEuPGJyPlZpZGltbyBzZSBu',
    'YSBJbnRlcmxpYmVydSE8L3N0cm9uZz48L3A+Cg=='
)) USING utf8mb4);

SET @lock_name := CONCAT('zuzi-blog-', SHA1(CONCAT(DATABASE(), ':', @blog_slug)));
SET @lock_acquired := GET_LOCK(@lock_name, 10);
START TRANSACTION;

SET @existing_count := (
    SELECT COUNT(*) FROM pages WHERE `group` = 'blog' AND slug = @blog_slug
);
SET @can_write := IF(@lock_acquired = 1 AND @existing_count <= 1, 1, 0);
SET @blog_id := (
    SELECT MIN(id) FROM pages WHERE `group` = 'blog' AND slug = @blog_slug
);

UPDATE pages SET
    title = @blog_title,
    short_description = @blog_short_description,
    description = @blog_description,
    meta_title = @blog_meta_title,
    meta_description = @blog_meta_description,
    keywords = @blog_keywords,
    image = @blog_image,
    publish_date = COALESCE(publish_date, @published_at),
    status = 1,
    updated_at = NOW()
WHERE id = @blog_id AND `group` = 'blog' AND slug = @blog_slug AND @can_write = 1;

INSERT INTO pages (
    category_id, `group`, subgroup, title, short_description, description,
    meta_title, meta_description, slug, keywords, image, publish_date,
    viewed, featured, related_products, status, created_at, updated_at
)
SELECT
    NULL, 'blog', NULL, @blog_title, @blog_short_description, @blog_description,
    @blog_meta_title, @blog_meta_description, @blog_slug, @blog_keywords,
    @blog_image, @published_at, 0, 0, NULL, 1, @published_at, NOW()
WHERE @blog_id IS NULL AND @can_write = 1;

SET @blog_id := (
    SELECT MIN(id) FROM pages WHERE `group` = 'blog' AND slug = @blog_slug
);
COMMIT;
SET @lock_released := RELEASE_LOCK(@lock_name);

SELECT
    @blog_id AS blog_id,
    @blog_slug AS slug,
    CONCAT('/blog/', @blog_slug) AS blog_path,
    @blog_image AS main_image,
    CASE
        WHEN COALESCE(@lock_acquired, 0) <> 1 THEN 'NOT WRITTEN: lock unavailable'
        WHEN @existing_count > 1 THEN 'NOT WRITTEN: duplicate existing slug'
        WHEN @existing_count = 0 THEN 'OK: created'
        ELSE 'OK: updated'
    END AS result;
