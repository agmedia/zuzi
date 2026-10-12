-- Osvjezi URL-ove samo dviju fotografija koje se nisu ucitale.
-- Datoteke su dostupne na serveru i ispravne; nova verzija URL-a
-- trazi svjezi odgovor umjesto prethodno spremljenog odgovora preglednika.
-- Postojeca live objava ima ID 254; pronalazi se po group + slug.
-- Ne mijenja tekst, naslov, datum objave ili druge fotografije.
-- Sigurno za ponavljanje: zamjenjuje samo tocne stare URL-ove s navodnikom.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @blog_slug := 'hvala-vam-splite-zuzihr-na-mediteranskom-festivalu-knjige-2026';
SET @lock_name := CONCAT('zuzi-blog-', SHA1(CONCAT(DATABASE(), ':', @blog_slug)));
SET @lock_acquired := GET_LOCK(@lock_name, 10);
START TRANSACTION;
SET @existing_count := (SELECT COUNT(*) FROM pages WHERE `group` = 'blog' AND slug = @blog_slug);
SET @blog_id := (SELECT MIN(id) FROM pages WHERE `group` = 'blog' AND slug = @blog_slug);

UPDATE pages
SET description = REPLACE(REPLACE(description,
    '/media/img/blog/mfk-2026-zahvala/uspomena-sa-zutom-knjigom.webp"',
    '/media/img/blog/mfk-2026-zahvala/uspomena-sa-zutom-knjigom.webp?v=20261012-2"'),
    '/media/img/blog/mfk-2026-zahvala/zajednicka-fotografija-s-knjigama.webp"',
    '/media/img/blog/mfk-2026-zahvala/zajednicka-fotografija-s-knjigama.webp?v=20261012-2"'),
    updated_at = NOW()
WHERE id = @blog_id AND `group` = 'blog' AND slug = @blog_slug
    AND @existing_count = 1 AND @lock_acquired = 1;
COMMIT;
SET @lock_released := RELEASE_LOCK(@lock_name);

SELECT @blog_id AS blog_id,
    CASE
        WHEN COALESCE(@lock_acquired, 0) <> 1 THEN 'NOT WRITTEN: lock unavailable'
        WHEN @existing_count = 0 THEN 'NOT WRITTEN: blog not found'
        WHEN @existing_count > 1 THEN 'NOT WRITTEN: duplicate slug'
        ELSE 'OK: image URLs refreshed'
    END AS result;
