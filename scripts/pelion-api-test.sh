#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${PELION_BASE_URL:-https://api.pelionpro.com/api/v1}"
API_KEY="${PELION_API_KEY:-}"

usage() {
  cat <<'USAGE'
PelionPro API lokalni tester

Upotreba:
  ./scripts/pelion-api-test.sh <akcija> [vrijednost]

Akcije:
  item-list                 -> GET /itemList
  item-list-attrs           -> GET /itemList?ItemAttributes=D
  item-by-id <id>           -> GET /itemList?ItemId=<id>
  group-items <groupId>     -> GET /itemList?ItemGroupId=<groupId>
  group-active <groupId>    -> GET /itemList?ItemGroupId=<groupId>&ItemActive=D
  item-type <T|K|U>         -> GET /itemList?ItemType=<type>
  item-groups               -> GET /itemGroupList
  stock-list                -> GET /stockList
  stock-by-item <id>        -> GET /stockList?ItemId=<id>

Varijable okoline:
  PELION_API_KEY            -> obavezno
  PELION_BASE_URL           -> opcionalno (default: https://api.pelionpro.com/api/v1)

Primjer:
  export PELION_API_KEY="tvoj-kljuc"
  ./scripts/pelion-api-test.sh item-list
USAGE
}

if [[ -z "$API_KEY" ]]; then
  echo "Greška: postavi PELION_API_KEY prije pokretanja skripte."
  echo "Primjer: export PELION_API_KEY=\"...\""
  exit 1
fi

if [[ ${1:-} == "" || ${1:-} == "-h" || ${1:-} == "--help" ]]; then
  usage
  exit 0
fi

action="$1"
value="${2:-}"
path=""

case "$action" in
  item-list)
    path="/itemList"
    ;;
  item-list-attrs)
    path="/itemList?ItemAttributes=D"
    ;;
  item-by-id)
    if [[ -z "$value" ]]; then
      echo "Greška: item-by-id treba ID, npr. ./scripts/pelion-api-test.sh item-by-id 1"
      exit 1
    fi
    path="/itemList?ItemId=$value"
    ;;
  group-items)
    if [[ -z "$value" ]]; then
      echo "Greška: group-items treba ItemGroupId, npr. ./scripts/pelion-api-test.sh group-items 23"
      exit 1
    fi
    path="/itemList?ItemGroupId=$value"
    ;;
  group-active)
    if [[ -z "$value" ]]; then
      echo "Greška: group-active treba ItemGroupId, npr. ./scripts/pelion-api-test.sh group-active 23"
      exit 1
    fi
    path="/itemList?ItemGroupId=$value&ItemActive=D"
    ;;
  item-type)
    if [[ -z "$value" ]]; then
      echo "Greška: item-type treba vrijednost T, K ili U, npr. ./scripts/pelion-api-test.sh item-type T"
      exit 1
    fi
    path="/itemList?ItemType=$value"
    ;;
  item-groups)
    path="/itemGroupList"
    ;;
  stock-list)
    path="/stockList"
    ;;
  stock-by-item)
    if [[ -z "$value" ]]; then
      echo "Greška: stock-by-item treba ItemId, npr. ./scripts/pelion-api-test.sh stock-by-item 1"
      exit 1
    fi
    path="/stockList?ItemId=$value"
    ;;
  *)
    echo "Greška: nepoznata akcija '$action'"
    usage
    exit 1
    ;;
esac

url="${BASE_URL}${path}"

echo "GET $url"

response="$(curl -sS -X GET "$url" \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: $API_KEY")"

if command -v jq >/dev/null 2>&1; then
  echo "$response" | jq .
else
  echo "$response"
fi
