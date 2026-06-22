# Pelion API za dohvat web narudzbi

API sluzi tome da korisnik u Pelion programu klikne gumb za dohvat narudzbi, vidi listu narudzbi spremnih za fakturiranje i zatim povuce odabranu narudzbu u Pelion.

Poveznica artikla je postojece polje `itemid`, odnosno Pelion ItemID. Pretpostavka je da svi artikli koji se fakturiraju imaju upisan `itemid`.

## Autentikacija

Svi pozivi koriste token:

```http
Authorization: Bearer PELION_INCOMING_TOKEN
```

Moze se koristiti i header:

```http
X-API-KEY: PELION_INCOMING_TOKEN
```

Na web aplikaciji treba postaviti:

```env
PELION_INCOMING_TOKEN=dogovoreni-token
PELION_ORDERS_FROM=2026-06-19
PELION_ORDER_PREFIX=WEB-
PELION_CURRENCY=EUR
PELION_DEFAULT_PRODUCT_TAX_RATE=5
PELION_SHIPPING_TAX_RATE=25
```

`PELION_ORDERS_FROM` je bitan jer u bazi postoji puno starih narudzbi. API po defaultu prikazuje samo narudzbe od tog datuma nadalje.

Ako se promjena baze radi rucno bez Laravel migracija, pokrenuti SQL:

```text
database/059_add_pelion_invoice_fields_to_orders_table.sql
```

## Tok rada u Pelionu

1. Korisnik klikne gumb za dohvat narudzbi.
2. Pelion pozove `GET /api/pelion/v1/orders`.
3. Pelion prikaze listu narudzbi.
4. Korisnik odabere narudzbu.
5. Pelion pozove `GET /api/pelion/v1/orders/{id}`.
6. Pelion iz detalja kreira racun.
7. Pelion pozove `POST /api/pelion/v1/orders/{id}/status` i vrati status.

Dohvat liste ne mijenja status narudzbe. Status se mijenja tek nakon povratnog poziva iz Peliona.

## Lista narudzbi

```http
GET /api/pelion/v1/orders?status=ready_for_invoice&limit=50
```

Parametri:

```text
status=ready_for_invoice | imported_to_pelion | invoiced | error | all
limit=1-100
page=1
query=WEB-12345 ili ime/email kupca
date_from=2026-06-19
date_to=2026-06-30
updated_from=2026-06-19T10:00:00
```

Primjer odgovora:

```json
{
  "data": [
    {
      "id": 12345,
      "order_number": "WEB-12345",
      "created_at": "2026-06-20T09:00:00+02:00",
      "updated_at": "2026-06-20T09:05:00+02:00",
      "customer_name": "Ivan Horvat",
      "payment_method_label": "Kartica",
      "shipping_method_label": "Dostava GLS",
      "shipping_price": 4,
      "total": 29,
      "currency": "EUR",
      "items_count": 2,
      "status": "ready_for_invoice"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total": 1,
    "last_page": 1
  }
}
```

## Detalj narudzbe

```http
GET /api/pelion/v1/orders/{id}
```

Primjer odgovora:

```json
{
  "data": {
    "id": 12345,
    "order_number": "WEB-12345",
    "created_at": "2026-06-20T09:00:00+02:00",
    "status": "ready_for_invoice",
    "customer": {
      "name": "Ivan Horvat",
      "company": null,
      "oib": null,
      "email": "ivan@example.com",
      "phone": "+385911234567",
      "address": "Ilica 1",
      "city": "Zagreb",
      "postal_code": "10000"
    },
    "items": [
      {
        "itemid": 123456,
        "article_id": 987,
        "sku": "KNJ-001",
        "title": "Naziv knjige",
        "quantity": 2,
        "unit_price": 12.5,
        "tax_rate": 5,
        "discount": 0,
        "line_total": 25
      }
    ],
    "shipping": {
      "method": "gls",
      "method_label": "Dostava GLS",
      "price": 4,
      "tax_rate": 25
    },
    "payment": {
      "method": "card",
      "method_label": "Kartica",
      "paid": true,
      "transaction_id": "PG-123"
    },
    "totals": {
      "items_total": 25,
      "shipping_total": 4,
      "discount_total": 0,
      "tax_total": 0,
      "grand_total": 29
    },
    "currency": "EUR"
  }
}
```

## Povrat statusa

```http
POST /api/pelion/v1/orders/{id}/status
```

Statusi:

```text
imported_to_pelion
invoiced
error
```

Primjer za fakturiranu narudzbu:

```json
{
  "status": "invoiced",
  "invoice_number": "R-2026-000456",
  "invoice_date": "2026-06-20"
}
```

## Artikli

```http
GET /api/pelion/v1/articles?limit=100
```

API vraca aktivne artikle s upisanim `itemid`. Podrzani su `query`, `active`, `updated_from`, `limit` i `page`.

## Nakladnici

```http
GET /api/pelion/v1/publishers?limit=100
```

API vraca aktivne nakladnike. Podrzani su `query`, `active`, `updated_from`, `limit` i `page`.

## Curl primjeri

```bash
curl -H "Authorization: Bearer $PELION_INCOMING_TOKEN" \
  "https://example.com/api/pelion/v1/orders?status=ready_for_invoice&limit=50"
```

```bash
curl -H "Authorization: Bearer $PELION_INCOMING_TOKEN" \
  "https://example.com/api/pelion/v1/orders/12345"
```

```bash
curl -X POST \
  -H "Authorization: Bearer $PELION_INCOMING_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"invoiced","invoice_number":"R-2026-000456","invoice_date":"2026-06-20"}' \
  "https://example.com/api/pelion/v1/orders/12345/status"
```
