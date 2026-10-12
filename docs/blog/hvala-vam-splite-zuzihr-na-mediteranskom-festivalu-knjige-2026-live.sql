-- ZUZI.hr: zahvala posjetiteljima Mediteranskog festivala knjige 2026.
-- 1. Prvo postaviti javne datoteke iz ova dva direktorija:
--    public/media/img/blog/mfk-2026-zahvala/
--    public/media/video/blog/mfk-2026-zahvala/
-- 2. Odabrati ZUZI bazu i pokrenuti ovaj cijeli SQL.
-- Postavlja naslov, HTML, glavnu sliku, sazetak, SEO i status objave.
-- Sigurno za ponavljanje: azurira istu objavu po slugu; ne brise podatke.
-- Kod ponavljanja cuva created_at, publish_date i viewed.
-- Ako isti slug vec postoji vise puta ili lock nije dostupan, ne pise nista.
-- Datum objave je trenutak prvog pokretanja u vremenskoj zoni baze.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @blog_slug := 'hvala-vam-splite-zuzihr-na-mediteranskom-festivalu-knjige-2026';
SET @blog_title := 'Hvala vam, Splite! ZUZI.hr na Mediteranskom festivalu knjige 2026.';
SET @blog_short_description := 'Hvala svima koji ste posjetili ZUZI štand na Mediteranskom festivalu knjige 2026. u Splitu! Donosimo fotografije i dva videa s Gripa.';
SET @blog_meta_title := 'Hvala vam, Splite! Mediteranski festival knjige 2026.';
SET @blog_meta_description := 'Hvala na posjetu ZUZI štandu na Mediteranskom festivalu knjige 2026. u Splitu! Pogledajte fotografije i dva videa s Gripa.';
SET @blog_keywords := 'ZUZI.hr, Mediteranski festival knjige 2026, MFK 2026, Split, Gripe, zahvala';
SET @blog_image := 'media/img/blog/mfk-2026-zahvala/zuzi-stand.jpg';
SET @published_at := NOW();
SET @blog_description := CONVERT(FROM_BASE64(CONCAT(
    'PHA+PHN0cm9uZz5IdmFsYSBzdmltYSBrb2ppIHN0ZSBuYXMgcG9zamV0aWxpIG5hIE1lZGl0ZXJhbnNrb20gZmVzdGl2YWx1IGtuamlnZSB1IFNwbGl0dSE8',
    'L3N0cm9uZz4gUGV0IGRhbmEgbmEgR3JpcGFtYSwgb2QgMjMuIGRvIDI3LiBydWpuYSAyMDI2LiwgcHJvdmVsaSBzbW8gdXogZHJ1xb5lbmplLCBsaXN0YW5q',
    'ZSBpIHJhemdvdm9yZSBvIGtuamlnYW1hLiBWZXNlbGkgbmFzIMWhdG8gamUgWlVaSS5ociBiaW8gZGlvIHRlIHByacSNZS48L3A+Cgo8cD5VIDxhIGhyZWY9',
    'Ii9ibG9nL3p1emloci1uYS1tZWRpdGVyYW5za29tLWZlc3RpdmFsdS1rbmppZ2UtMjAyNi12aWRpbW8tc2UtdS1zcGxpdHUiPm5hamF2aSBuYcWhZWcgZG9s',
    'YXNrYSB1IFNwbGl0PC9hPiBwb3p2YWxpIHNtbyB2YXMgZGEgbmFzIHVwb3puYXRlIGkgaXp2YW4gaW50ZXJuZXRhLiBIdmFsYSDFoXRvIHN0ZSBzZSBvZGF6',
    'dmFsaSwgemFzdGFsaSB1eiBuYcWhIMWhdGFuZCBpIHByb3ZlbGkgZGlvIHN2b2plZyBmZXN0aXZhbHNrb2cgZGFuYSBzIG5hbWEuPC9wPgoKPGgyPkh2YWxh',
    'IG5hIHN2YWtvbSBkb2xhc2t1PC9oMj4KCjxwPkh2YWxhIHZhbSBuYSBwb3ZqZXJlbmp1LCBwaXRhbmppbWEgaSBwcmVwb3J1a2FtYSwgbmEgc3Zha29tIHBy',
    'ZWxpc3Rhbm9tIG5hc2xvdnUgaSByYXpnb3ZvcnUuIEJpbG8gZGEgc3RlIGRvxaFsaSBzIHBvcGlzb20gxb5lbGphLCB0cmHFvmlsaSBrbmppZ3UgemEgcG9r',
    'bG9uIGlsaSBzYW1vIHJhemdsZWRhdmFsaSBwb251ZHUsIGJpbG8gbmFtIGplIGRyYWdvIHN1c3Jlc3RpIHZhcyBtZcSRdSBrbmppZ2FtYS48L3A+Cgo8cD5O',
    'YWRhbW8gc2UgZGEgc3Uga25qaWdlIGtvamUgc3RlIHBvbmlqZWxpIGt1xIdpIHZlxIcgcHJvbmHFoWxlIG1qZXN0byBuYSB2YcWhaW0gcG9saWNhbWEg4oCU',
    'IGlsaSwgam/FoSBib2xqZSwgZGEgaWggdXByYXZvIMSNaXRhdGUuIEEgYWtvIHN0ZSBuYXMgbmEgZmVzdGl2YWx1IHRlayB1cG96bmFsaSwgZG9icm8gbmFt',
    'IGRvxaFsaSB1IFpVWkkgZHJ1xaF0dm8hPC9wPgoKPGgyPk9kIHBydmloIGt1dGlqYSBkbyDFoXRhbmRhIHB1bm9nIGtuamlnYTwvaDI+Cgo8cD5QcmlqZSBz',
    'dXNyZXRhIHMgcG9zamV0aXRlbGppbWEgZG/FoWxlIHN1IGt1dGlqZSwgc2xhZ2FuamUga25qaWdhIGkgcHJpcHJlbWEgxaF0YW5kYS4gTmEgZm90b2dyYWZp',
    'amFtYSBzbW8gemFiaWxqZcW+aWxpIGkgdGFqIGRpbyBuYcWhZWcgYm9yYXZrYSBuYSBHcmlwYW1hOiBvZCBwcmF6bmloIHN0b2xvdmEgZG8gcmVkb3ZhIG5h',
    'c2xvdm5pY2Egc3ByZW1uaWggemEgxI1pdGF0ZWxqZS48L3A+Cgo8ZmlndXJlIHN0eWxlPSJtYXJnaW46IDEuNXJlbSAwOyI+CiAgICA8YSBocmVmPSIvbWVk',
    'aWEvaW1nL2Jsb2cvbWZrLTIwMjYtemFodmFsYS9wcmlwcmVtYS1zdGFuZGEud2VicCIgYXJpYS1sYWJlbD0iT3R2b3JpIHZlxId1IGZvdG9ncmFmaWp1IHBy',
    'aXByZW1lIFpVWkkgxaF0YW5kYSI+CiAgICAgICAgPGltZyBzcmM9Ii9tZWRpYS9pbWcvYmxvZy9tZmstMjAyNi16YWh2YWxhL3ByaXByZW1hLXN0YW5kYS53',
    'ZWJwIiBhbHQ9IlpVWkkgU2hvcCDFoXRhbmQgcyBwcmF6bmltIHN0b2xvdmltYSBpIGt1dGlqYW1hIGtuamlnYSB0aWpla29tIHByaXByZW1lIGZlc3RpdmFs',
    'YSIgd2lkdGg9IjE2MDAiIGhlaWdodD0iOTAwIiBsb2FkaW5nPSJsYXp5IiBkZWNvZGluZz0iYXN5bmMiIHN0eWxlPSJkaXNwbGF5OiBibG9jazsgd2lkdGg6',
    'IDEwMCU7IGhlaWdodDogYXV0bzsgYm9yZGVyLXJhZGl1czogLjVyZW07Ij4KICAgIDwvYT4KICAgIDxmaWdjYXB0aW9uIHN0eWxlPSJtYXJnaW4tdG9wOiAu',
    'NXJlbTsgY29sb3I6ICM1OTY0N2E7IGZvbnQtc2l6ZTogLjlyZW07Ij5QcmlwcmVtZSBuYSBHcmlwYW1hOiBrbmppZ2Ugc3Ugc3RpZ2xlLCBzbGlqZWRpIHNs',
    'YWdhbmplIMWhdGFuZGEuPC9maWdjYXB0aW9uPgo8L2ZpZ3VyZT4KCjxmaWd1cmUgc3R5bGU9Im1heC13aWR0aDogNDIwcHg7IG1hcmdpbjogMS41cmVtIGF1',
    'dG87Ij4KICAgIDxhIGhyZWY9Ii9tZWRpYS9pbWcvYmxvZy9tZmstMjAyNi16YWh2YWxhL2tuamlnZS1pLWt1dGlqZS53ZWJwIiBhcmlhLWxhYmVsPSJPdHZv',
    'cmkgdmXEh3UgZm90b2dyYWZpanUgc2xhZ2FuamEga25qaWdhIj4KICAgICAgICA8aW1nIHNyYz0iL21lZGlhL2ltZy9ibG9nL21may0yMDI2LXphaHZhbGEv',
    'a25qaWdlLWkta3V0aWplLndlYnAiIGFsdD0iUG9nbGVkIHMgdHJpYmluYSBuYSDFoXRhbmRvdmUsIGl6bG/FvmVuZSBrbmppZ2UgaSBrdXRpamUgdGlqZWtv',
    'bSBwcmlwcmVtYSB1IGR2b3JhbmkgR3JpcGUiIHdpZHRoPSI5MDAiIGhlaWdodD0iMTYwMCIgbG9hZGluZz0ibGF6eSIgZGVjb2Rpbmc9ImFzeW5jIiBzdHls',
    'ZT0iZGlzcGxheTogYmxvY2s7IHdpZHRoOiAxMDAlOyBoZWlnaHQ6IGF1dG87IGJvcmRlci1yYWRpdXM6IC41cmVtOyI+CiAgICA8L2E+CiAgICA8ZmlnY2Fw',
    'dGlvbiBzdHlsZT0ibWFyZ2luLXRvcDogLjVyZW07IGNvbG9yOiAjNTk2NDdhOyBmb250LXNpemU6IC45cmVtOyI+UG9nbGVkIG5hIGZlc3RpdmFsc2tpIHBy',
    'b3N0b3IgdGlqZWtvbSBwcmlwcmVtYS48L2ZpZ2NhcHRpb24+CjwvZmlndXJlPgoKPGgyPlN1c3JldGkgaSB0cmVudWNpIHMgR3JpcGE8L2gyPgoKPHA+S25q',
    'aWdlIHN1IG5hcyBkb3ZlbGUgdSBTcGxpdCwgYSBzdXNyZXRpIHMgdmFtYSBvbm8gc3UgxI1lbXUgc2UgcG9zZWJubyB2ZXNlbGltby4gRG9ub3NpbW8gbmVr',
    'b2xpa28gZm90b2dyYWZpamEga2FvIHVzcG9tZW51IG5hIG5hxaEgYm9yYXZhayBuYSBmZXN0aXZhbHUuPC9wPgoKPGZpZ3VyZSBzdHlsZT0ibWFyZ2luOiAx',
    'LjVyZW0gMDsiPgogICAgPGEgaHJlZj0iL21lZGlhL2ltZy9ibG9nL21may0yMDI2LXphaHZhbGEvemFqZWRuaWNrYS1mb3RvZ3JhZmlqYS53ZWJwIiBhcmlh',
    'LWxhYmVsPSJPdHZvcmkgdmXEh3UgemFqZWRuacSNa3UgZm90b2dyYWZpanUgbmEgWlVaSSDFoXRhbmR1Ij4KICAgICAgICA8aW1nIHNyYz0iL21lZGlhL2lt',
    'Zy9ibG9nL21may0yMDI2LXphaHZhbGEvemFqZWRuaWNrYS1mb3RvZ3JhZmlqYS53ZWJwIiBhbHQ9IlphamVkbmnEjWthIGZvdG9ncmFmaWphIHRyb2plIGxq',
    'dWRpIGlzcHJlZCBrbmppZ2EgaSBuYXRwaXNhIFpVWkkgU2hvcCIgd2lkdGg9IjE2MDAiIGhlaWdodD0iOTAwIiBsb2FkaW5nPSJsYXp5IiBkZWNvZGluZz0i',
    'YXN5bmMiIHN0eWxlPSJkaXNwbGF5OiBibG9jazsgd2lkdGg6IDEwMCU7IGhlaWdodDogYXV0bzsgYm9yZGVyLXJhZGl1czogLjVyZW07Ij4KICAgIDwvYT4K',
    'ICAgIDxmaWdjYXB0aW9uIHN0eWxlPSJtYXJnaW4tdG9wOiAuNXJlbTsgY29sb3I6ICM1OTY0N2E7IGZvbnQtc2l6ZTogLjlyZW07Ij5Vc3BvbWVuYSBzYSBa',
    'VVpJIMWhdGFuZGEgdSBTcGxpdHUuPC9maWdjYXB0aW9uPgo8L2ZpZ3VyZT4KCjxkaXYgY2xhc3M9InJvdyI+CiAgICA8ZGl2IGNsYXNzPSJjb2wtNiI+CiAg',
    'ICAgICAgPGZpZ3VyZSBzdHlsZT0ibWFyZ2luOiAwIDAgMS41cmVtOyI+CiAgICAgICAgICAgIDxhIGhyZWY9Ii9tZWRpYS9pbWcvYmxvZy9tZmstMjAyNi16',
    'YWh2YWxhL21lZHUta25qaWdhbWEud2VicCIgYXJpYS1sYWJlbD0iT3R2b3JpIHZlxId1IGZvdG9ncmFmaWp1IHNhIFpVWkkgxaF0YW5kYSI+CiAgICAgICAg',
    'ICAgICAgICA8aW1nIHNyYz0iL21lZGlhL2ltZy9ibG9nL21may0yMDI2LXphaHZhbGEvbWVkdS1rbmppZ2FtYS53ZWJwIiBhbHQ9IsSMbGFuIFpVWkkgZWtp',
    'cGUgaXphIHN0b2xhIHB1bm9nIGtuamlnYSBwb2QgbmF0cGlzb20gWlVaSSBTaG9wIiB3aWR0aD0iMTA2NiIgaGVpZ2h0PSIxNjAwIiBsb2FkaW5nPSJsYXp5',
    'IiBkZWNvZGluZz0iYXN5bmMiIHN0eWxlPSJkaXNwbGF5OiBibG9jazsgd2lkdGg6IDEwMCU7IGhlaWdodDogYXV0bzsgYm9yZGVyLXJhZGl1czogLjVyZW07',
    'Ij4KICAgICAgICAgICAgPC9hPgogICAgICAgICAgICA8ZmlnY2FwdGlvbiBzdHlsZT0ibWFyZ2luLXRvcDogLjVyZW07IGNvbG9yOiAjNTk2NDdhOyBmb250',
    'LXNpemU6IC45cmVtOyI+TWXEkXUga25qaWdhbWEgbmEgbmHFoWVtIMWhdGFuZHUuPC9maWdjYXB0aW9uPgogICAgICAgIDwvZmlndXJlPgogICAgPC9kaXY+',
    'CiAgICA8ZGl2IGNsYXNzPSJjb2wtNiI+CiAgICAgICAgPGZpZ3VyZSBzdHlsZT0ibWFyZ2luOiAwIDAgMS41cmVtOyI+CiAgICAgICAgICAgIDxhIGhyZWY9',
    'Ii9tZWRpYS9pbWcvYmxvZy9tZmstMjAyNi16YWh2YWxhL3N1c3JldC1uYS1zdGFuZHUud2VicCIgYXJpYS1sYWJlbD0iT3R2b3JpIHZlxId1IGZvdG9ncmFm',
    'aWp1IHN1c3JldGEgbmEgWlVaSSDFoXRhbmR1Ij4KICAgICAgICAgICAgICAgIDxpbWcgc3JjPSIvbWVkaWEvaW1nL2Jsb2cvbWZrLTIwMjYtemFodmFsYS9z',
    'dXNyZXQtbmEtc3RhbmR1LndlYnAiIGFsdD0iRHZpamUgxb5lbmUgcG96aXJhanUgaXNwcmVkIFpVWkkgU2hvcCDFoXRhbmRhIG5hIE1lZGl0ZXJhbnNrb20g',
    'ZmVzdGl2YWx1IGtuamlnZSIgd2lkdGg9IjkwMSIgaGVpZ2h0PSIxNjAwIiBsb2FkaW5nPSJsYXp5IiBkZWNvZGluZz0iYXN5bmMiIHN0eWxlPSJkaXNwbGF5',
    'OiBibG9jazsgd2lkdGg6IDEwMCU7IGhlaWdodDogYXV0bzsgYm9yZGVyLXJhZGl1czogLjVyZW07Ij4KICAgICAgICAgICAgPC9hPgogICAgICAgICAgICA8',
    'ZmlnY2FwdGlvbiBzdHlsZT0ibWFyZ2luLXRvcDogLjVyZW07IGNvbG9yOiAjNTk2NDdhOyBmb250LXNpemU6IC45cmVtOyI+U3VzcmV0aSBrb2plIMSHZW1v',
    'IHBhbXRpdGkuPC9maWdjYXB0aW9uPgogICAgICAgIDwvZmlndXJlPgogICAgPC9kaXY+CjwvZGl2PgoKPGZpZ3VyZSBzdHlsZT0ibWFyZ2luOiAwIDAgMS41',
    'cmVtOyI+CiAgICA8YSBocmVmPSIvbWVkaWEvaW1nL2Jsb2cvbWZrLTIwMjYtemFodmFsYS9wb3NqZXRpdGVsamktaS1rbmppZ2Uud2VicCIgYXJpYS1sYWJl',
    'bD0iT3R2b3JpIHZlxId1IGZvdG9ncmFmaWp1IHBvc2pldGl0ZWxqYSBtZcSRdSBrbmppZ2FtYSI+CiAgICAgICAgPGltZyBzcmM9Ii9tZWRpYS9pbWcvYmxv',
    'Zy9tZmstMjAyNi16YWh2YWxhL3Bvc2pldGl0ZWxqaS1pLWtuamlnZS53ZWJwIiBhbHQ9IlBvc2pldGl0ZWxqaSBwcmVnbGVkYXZhanUgaSBsaXN0YWp1IGtu',
    'amlnZSB1eiBaVVpJIFNob3AgxaF0YW5kIG5hIGZlc3RpdmFsdSIgd2lkdGg9IjE2MDAiIGhlaWdodD0iMTA2NiIgbG9hZGluZz0ibGF6eSIgZGVjb2Rpbmc9',
    'ImFzeW5jIiBzdHlsZT0iZGlzcGxheTogYmxvY2s7IHdpZHRoOiAxMDAlOyBoZWlnaHQ6IGF1dG87IGJvcmRlci1yYWRpdXM6IC41cmVtOyI+CiAgICA8L2E+',
    'CiAgICA8ZmlnY2FwdGlvbiBzdHlsZT0ibWFyZ2luLXRvcDogLjVyZW07IGNvbG9yOiAjNTk2NDdhOyBmb250LXNpemU6IC45cmVtOyI+VnJpamVtZSB6YSBy',
    'YXpnbGVkYXZhbmplIGkgbGlzdGFuamUga25qaWdhLjwvZmlnY2FwdGlvbj4KPC9maWd1cmU+Cgo8ZmlndXJlIHN0eWxlPSJtYXJnaW46IDAgMCAxLjVyZW07',
    'Ij4KICAgIDxhIGhyZWY9Ii9tZWRpYS9pbWcvYmxvZy9tZmstMjAyNi16YWh2YWxhL3BvbnVkYS1uYS1zdGFuZHUud2VicCIgYXJpYS1sYWJlbD0iT3R2b3Jp',
    'IHZlxId1IGZvdG9ncmFmaWp1IGtuamlnYSBuYSBaVVpJIMWhdGFuZHUiPgogICAgICAgIDxpbWcgc3JjPSIvbWVkaWEvaW1nL2Jsb2cvbWZrLTIwMjYtemFo',
    'dmFsYS9wb251ZGEtbmEtc3RhbmR1LndlYnAiIGFsdD0ixIxsYW4gWlVaSSBla2lwZSB1eiBpemxvxb5lbmUgcm9tYW5lIGkgZHJ1Z2Uga25qaWdlIG5hIMWh',
    'dGFuZHUiIHdpZHRoPSIxNjAwIiBoZWlnaHQ9IjEwNjYiIGxvYWRpbmc9ImxhenkiIGRlY29kaW5nPSJhc3luYyIgc3R5bGU9ImRpc3BsYXk6IGJsb2NrOyB3',
    'aWR0aDogMTAwJTsgaGVpZ2h0OiBhdXRvOyBib3JkZXItcmFkaXVzOiAuNXJlbTsiPgogICAgPC9hPgogICAgPGZpZ2NhcHRpb24gc3R5bGU9Im1hcmdpbi10',
    'b3A6IC41cmVtOyBjb2xvcjogIzU5NjQ3YTsgZm9udC1zaXplOiAuOXJlbTsiPkRqZWxpxIcgcG9udWRlIGtvanUgc21vIGRvbmlqZWxpIHUgU3BsaXQuPC9m',
    'aWdjYXB0aW9uPgo8L2ZpZ3VyZT4KCjxoMj5Qcm/FoWV0YWp0ZSBuYcWhaW0gxaF0YW5kb208L2gyPgoKPHA+VXogZm90b2dyYWZpamUsIGRvbm9zaW1vIGkg',
    'ZHZhIGtyYXRrYSB2aWRlYSB6YSBqb8WhIG1hbG8gZmVzdGl2YWxza2UgYXRtb3NmZXJlLjwvcD4KCjxkaXYgY2xhc3M9InJvdyI+CiAgICA8ZGl2IGNsYXNz',
    'PSJjb2wtMTIgY29sLW1kLTYiPgogICAgICAgIDxmaWd1cmUgc3R5bGU9Im1heC13aWR0aDogMzYwcHg7IG1hcmdpbjogMCBhdXRvIDEuNXJlbTsiPgogICAg',
    'ICAgICAgICA8dmlkZW8gY29udHJvbHMgcGxheXNpbmxpbmUgcHJlbG9hZD0ibm9uZSIgcG9zdGVyPSIvbWVkaWEvaW1nL2Jsb2cvbWZrLTIwMjYtemFodmFs',
    'YS92aWRlby0xLXBvc3Rlci53ZWJwIiB3aWR0aD0iNDc4IiBoZWlnaHQ9Ijg1MCIgYXJpYS1sYWJlbD0iVmlkZW86IGtuamlnZSBuYSBaVVpJIMWhdGFuZHUg',
    'bmEgTWVkaXRlcmFuc2tvbSBmZXN0aXZhbHUga25qaWdlIDIwMjYuIiBzdHlsZT0iZGlzcGxheTogYmxvY2s7IHdpZHRoOiAxMDAlOyBoZWlnaHQ6IGF1dG87',
    'IGJvcmRlci1yYWRpdXM6IC41cmVtOyBiYWNrZ3JvdW5kOiAjMTExOyI+CiAgICAgICAgICAgICAgICA8c291cmNlIHNyYz0iL21lZGlhL3ZpZGVvL2Jsb2cv',
    'bWZrLTIwMjYtemFodmFsYS96dXppLXN0YW5kLTEubXA0IiB0eXBlPSJ2aWRlby9tcDQiPgogICAgICAgICAgICAgICAgVmHFoSBwcmVnbGVkbmlrIG5lIHBv',
    'ZHLFvmF2YSBwcmlrYXogdmlkZWEuCiAgICAgICAgICAgIDwvdmlkZW8+CiAgICAgICAgICAgIDxmaWdjYXB0aW9uIHN0eWxlPSJtYXJnaW4tdG9wOiAuNXJl',
    'bTsgY29sb3I6ICM1OTY0N2E7IGZvbnQtc2l6ZTogLjlyZW07Ij5LbmppZ2UgaSBhdG1vc2ZlcmEgbmEgWlVaSSDFoXRhbmR1IMK3IDM5IHNla3VuZGkuIDxh',
    'IGhyZWY9Ii9tZWRpYS92aWRlby9ibG9nL21may0yMDI2LXphaHZhbGEvenV6aS1zdGFuZC0xLm1wNCI+T3R2b3JpIHZpZGVvPC9hPjwvZmlnY2FwdGlvbj4K',
    'ICAgICAgICA8L2ZpZ3VyZT4KICAgIDwvZGl2PgogICAgPGRpdiBjbGFzcz0iY29sLTEyIGNvbC1tZC02Ij4KICAgICAgICA8ZmlndXJlIHN0eWxlPSJtYXgt',
    'd2lkdGg6IDM2MHB4OyBtYXJnaW46IDAgYXV0byAxLjVyZW07Ij4KICAgICAgICAgICAgPHZpZGVvIGNvbnRyb2xzIHBsYXlzaW5saW5lIHByZWxvYWQ9Im5v',
    'bmUiIHBvc3Rlcj0iL21lZGlhL2ltZy9ibG9nL21may0yMDI2LXphaHZhbGEvdmlkZW8tMi1wb3N0ZXIud2VicCIgd2lkdGg9IjQ3OCIgaGVpZ2h0PSI4NTAi',
    'IGFyaWEtbGFiZWw9IlZpZGVvOiBwb2dsZWQgbmEgcG9saWNlIGkga25qaWdlIG5hIFpVWkkgxaF0YW5kdSB1IFNwbGl0dSIgc3R5bGU9ImRpc3BsYXk6IGJs',
    'b2NrOyB3aWR0aDogMTAwJTsgaGVpZ2h0OiBhdXRvOyBib3JkZXItcmFkaXVzOiAuNXJlbTsgYmFja2dyb3VuZDogIzExMTsiPgogICAgICAgICAgICAgICAg',
    'PHNvdXJjZSBzcmM9Ii9tZWRpYS92aWRlby9ibG9nL21may0yMDI2LXphaHZhbGEvenV6aS1zdGFuZC0yLm1wNCIgdHlwZT0idmlkZW8vbXA0Ij4KICAgICAg',
    'ICAgICAgICAgIFZhxaEgcHJlZ2xlZG5payBuZSBwb2Ryxb5hdmEgcHJpa2F6IHZpZGVhLgogICAgICAgICAgICA8L3ZpZGVvPgogICAgICAgICAgICA8Zmln',
    'Y2FwdGlvbiBzdHlsZT0ibWFyZ2luLXRvcDogLjVyZW07IGNvbG9yOiAjNTk2NDdhOyBmb250LXNpemU6IC45cmVtOyI+UG9nbGVkIG5hIG5hxaEgxaF0YW5k',
    'IMK3IDEgbWludXRhIGkgMTkgc2VrdW5kaS4gPGEgaHJlZj0iL21lZGlhL3ZpZGVvL2Jsb2cvbWZrLTIwMjYtemFodmFsYS96dXppLXN0YW5kLTIubXA0Ij5P',
    'dHZvcmkgdmlkZW88L2E+PC9maWdjYXB0aW9uPgogICAgICAgIDwvZmlndXJlPgogICAgPC9kaXY+CjwvZGl2PgoKPGgyPlZpZGltbyBzZSBtZcSRdSBrbmpp',
    'Z2FtYTwvaDI+Cgo8cD5IdmFsYSBpIG9yZ2FuaXphdG9yaW1hIE1lZGl0ZXJhbnNrb2cgZmVzdGl2YWxhIGtuamlnZSB0ZSBzdmltYSBrb2ppIHN1IHN1ZGpl',
    'bG92YWxpIHUgcHJpcHJlbWkgaSByYWR1IG5hxaFlZyDFoXRhbmRhLjwvcD4KCjxwPkRvIHNsamVkZcSHZWcgc3VzcmV0YSwgZHJ1xb5pbW8gc2UgbmEgPGEg',
    'aHJlZj0iaHR0cHM6Ly93d3cuenV6aS5oci8iPlpVWkkuaHI8L2E+LCBnZGplIHZhcyDEjWVrYWp1IGtuamlnZSB6YSBub3ZlIMSNaXRhdGVsanNrZSBhdmFu',
    'dHVyZS48L3A+Cgo8cD48c3Ryb25nPkh2YWxhIHZhbSwgU3BsaXRlLCBpIHNyZXRubyDEjWl0YW5qZSE8YnI+VmHFoWEgWlVaSSBla2lwYTwvc3Ryb25nPjwv',
    'cD4K'
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
