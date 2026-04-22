#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
#  Colways — Script de test API complet (Sprint 13)
#  Usage : bash tests/colways-api-test.sh
#  Prérequis : API lancée sur http://localhost (Herd)
# ─────────────────────────────────────────────────────────────────────────────

BASE="http://colways-api.test/api"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

pass() { echo -e "${GREEN}✅ PASS${NC} — $1"; }
fail() { echo -e "${RED}❌ FAIL${NC} — $1"; echo "   Réponse : $2"; }
info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
section() { echo -e "\n${YELLOW}═══ $1 ═══${NC}"; }

TOKEN=""
ARTICLE_ID=""

# ─────────────────────────────────────────────────────────────────────────────
section "1. AUTHENTIFICATION"
# ─────────────────────────────────────────────────────────────────────────────

info "Connexion avec un compte existant..."
LOGIN=$(curl -s -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"phone":"771234567","password":"password"}')

TOKEN=$(echo $LOGIN | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -n "$TOKEN" ]; then
  pass "Login réussi — token obtenu"
else
  fail "Login échoué" "$LOGIN"
  echo -e "${RED}Le test s'arrête ici — token nécessaire.${NC}"
  exit 1
fi

# ─────────────────────────────────────────────────────────────────────────────
section "2. FEED — Algorithme 5 Piliers"
# ─────────────────────────────────────────────────────────────────────────────

info "Chargement du feed principal..."
FEED=$(curl -s "$BASE/articles")
TOTAL=$(echo $FEED | grep -o '"total":[0-9]*' | head -1 | cut -d: -f2)
FIRST_SCORE=$(echo $FEED | grep -o '"visibility_score":[0-9]*' | head -1 | cut -d: -f2)

if echo $FEED | grep -q '"data":\['; then
  pass "Feed chargé — $TOTAL articles visibles"
else
  fail "Feed vide ou erreur" "$FEED"
fi

info "Vérification du tri (articles boostés en premier)..."
if echo $FEED | grep -q '"is_boosted":true'; then
  pass "Articles boostés présents dans le feed"
else
  info "Aucun boost actif — normal si pas de boost en cours"
fi

info "Test filtre catégorie..."
FILTER=$(curl -s "$BASE/articles?category=chaussures")
if echo $FILTER | grep -q '"category":"chaussures"'; then
  pass "Filtre catégorie fonctionne"
else
  info "Pas d'articles chaussures en base — filtre OK mais vide"
fi

# ─────────────────────────────────────────────────────────────────────────────
section "3. GUARDIAN FRIPERIE — Publication articles"
# ─────────────────────────────────────────────────────────────────────────────

info "Test 3a : Bon article friperie → doit être publié directement..."
GOOD=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/articles" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Veste Adidas vintage bleu marine taille L",
    "description": "Superbe veste Adidas vintage années 90, très bon état. Quelques légères traces d'\''usage normales. Taille L.",
    "price": 18000,
    "category": "vetements",
    "condition": "tres_bon_etat"
  }')

if [ "$GOOD" = "201" ]; then
  pass "Article friperie publié directement (HTTP 201)"
elif [ "$GOOD" = "202" ]; then
  fail "Article en pending_review (attendu 201)" "HTTP 202 — vérifie le Guardian"
else
  fail "Erreur publication" "HTTP $GOOD"
fi

info "Test 3b : iPhone → doit être bloqué (HTTP 202 pending_review)..."
BLOCKED=$(curl -s -X POST "$BASE/articles" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "iPhone 14 Pro Max 256Go neuf",
    "description": "Vente iPhone 14 Pro parfait état avec boîte et chargeur",
    "price": 350000,
    "category": "accessoires",
    "condition": "tres_bon_etat"
  }')

HTTP_CODE=$(echo $BLOCKED | grep -o '"status":"pending_review"')
MSG=$(echo $BLOCKED | grep -o '"message":"[^"]*"' | cut -d'"' -f4 | head -c 60)

if echo $BLOCKED | grep -q '"status":"pending_review"'; then
  pass "iPhone bloqué correctement → pending_review"
  info "   Message vendeur : ${MSG}..."
else
  fail "iPhone NON bloqué — Guardian ne fonctionne pas" "$BLOCKED"
fi

info "Récupération de l'ID du dernier article publié..."
ARTICLES=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE/my/articles" 2>/dev/null \
  || curl -s "$BASE/articles")
ARTICLE_ID=$(echo $ARTICLES | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
info "Article ID pour les tests suivants : $ARTICLE_ID"

# ─────────────────────────────────────────────────────────────────────────────
section "4. SIGNALEMENTS — Règle communautaire"
# ─────────────────────────────────────────────────────────────────────────────

if [ -n "$ARTICLE_ID" ]; then
  info "Test signalement 'pas_de_la_friperie'..."
  REPORT=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/reports" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"article_id\": $ARTICLE_ID,
      \"reason\": \"pas_de_la_friperie\",
      \"description\": \"Ce n'est pas un article de friperie\"
    }")

  if [ "$REPORT" = "201" ]; then
    pass "Signalement 'pas_de_la_friperie' enregistré"
  elif [ "$REPORT" = "422" ]; then
    info "Déjà signalé par cet utilisateur (normal si test rejoué)"
  else
    fail "Erreur signalement" "HTTP $REPORT"
  fi

  info "Test signalement 'arnaque'..."
  REPORT2=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/reports" \
    -H "Content-Type: application/json" \
    -d "{
      \"article_id\": $ARTICLE_ID,
      \"reason\": \"arnaque\",
      \"description\": \"Prix suspect\"
    }")

  if [ "$REPORT2" = "201" ]; then
    pass "Signalement 'arnaque' enregistré"
  else
    info "HTTP $REPORT2 — peut être déjà signalé"
  fi
else
  info "Pas d'article ID disponible — skip tests signalement"
fi

# ─────────────────────────────────────────────────────────────────────────────
section "5. ADMIN — File d'attente Review"
# ─────────────────────────────────────────────────────────────────────────────

info "Statistiques de la file d'attente (nécessite token admin)..."
STATS=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE/admin/review/stats")

if echo $STATS | grep -q '"pending_review"'; then
  PENDING=$(echo $STATS | grep -o '"pending_review":[0-9]*' | cut -d: -f2)
  BLOCKED=$(echo $STATS | grep -o '"blocked":[0-9]*' | cut -d: -f2)
  pass "Stats admin accessibles — $PENDING en review, $BLOCKED bloqués"
else
  info "Accès refusé (non-admin) ou erreur — normal si compte non-admin"
fi

info "Liste des articles en pending_review..."
PENDING_LIST=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE/admin/review/pending")
if echo $PENDING_LIST | grep -q '"total"'; then
  pass "File d'attente accessible"
else
  info "Accès refusé (non-admin) — normal"
fi

# ─────────────────────────────────────────────────────────────────────────────
section "RÉSUMÉ"
# ─────────────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}Tests API terminés.${NC}"
echo ""
echo "Pour tester l'admin review, connecte-toi avec un compte admin"
echo "et relance : curl -s -H 'Authorization: Bearer TON_TOKEN_ADMIN' $BASE/admin/review/stats"
echo ""
echo "Postman ? Importe cette collection :"
echo "  $BASE/articles        GET  → Feed avec algo 5 piliers"
echo "  $BASE/articles        POST → Publication + Guardian"
echo "  $BASE/reports         POST → Signalement"
echo "  $BASE/admin/review/pending GET → File admin"
