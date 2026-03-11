# Rapport d'Optimisation : Smart Link Checker (Vitesse, Sécurité, Architecture)

## État des lieux actuel (Est-ce optimal ?)

Le plugin a déjà fait d'énormes progrès lors du **Sprint 3 (Performance)** : le passage sur `Action Scheduler`, le parallélisme via `WpOrg\Requests\Requests::request_multiple`, le système de lots (batches) de 20, ou encore l'évitement du HTML brut si Gutenberg est actif.

C'est excellent et largement supérieur à 90% des plugins du marché. **Mais ce n'est pas encore optimal pour de très gros sites sur de petits hébergements mutualisés.** 

Voici pourquoi, et comment on peut aller plus loin :

---

## 🚀 Pistes d'Optimisations Majeures (Proposées)

### 1. Le Piège du "Self-DDoS" (Vérification des liens internes)
> **Le problème :** Actuellement, si l'outil trouve 10 liens internes (ex: `/contact/`, `/logo.png`), il va envoyer 5 requêtes HTTP parallèles vers le **propre serveur**. Sur un VPS à 2 vCore/2Go, cela signifie que 5 "workers" PHP / Apache vont être invoqués en même temps juste pour se parler à eux-mêmes. Le serveur peut très rapidement s'étouffer ou renvoyer des erreurs `503 Service Unavailable`.
> **La solution :**
> - **Bypass HTTP complet :** Si le lien est interne (`is_external === false`), **NE PAS** faire de requête HTTP.
> - Si c'est une page/article : Utiliser les fonctions natives très rapides de WordPress (`url_to_postid()` ou `get_page_by_path()`). Temps de réponse divisé par 100, consommation mémoire divisée par 100.
> - Si c'est un média/image : Vérifier l'existence physique du fichier via `file_exists()` (accès disque brutal instantané).

### 2. Rate-Limiting Sortant "Courtois" (Anti-Ban IP)
> **Le problème :** Si vous tirez 5 requêtes parallèles sur `amazon.fr` ou `fnac.com`, leurs pare-feux (Cloudflare, Akamai) vont détecter un comportement de scraping de la part de l'IP du serveur WordPress. Cela se sanctionne par un ban HTTP `403` ou un `CAPTCHA`, ruinant la fiabilité de l'outil et nuisant à l'IP globale du site e-commerce.
> **La solution :**
> - Regrouper (Grouper/Trier) les lots de vérification par nom de domaine dans le `CheckJob`.
> - S'il y a plus de 2 liens du même domaine dans le lot actuel, on tire 1 ou 2 requêtes simultanées maximum à l'instant T pour ce domaine précis (ou on intercale des `sleep` de 500ms).

### 3. Asymétrie des Timeouts (Libération des processus)
> **Le problème :** Actuellement, le système attend passivement selon un délai général. Sauf qu'un `HEAD` devrait répondre en quelques millisecondes. S'il bloque 15 secondes, on bloque le thread.
> **La solution :**
> - Forcer la requête `HEAD` à échouer très vite (ex: Timeout strict de `3` ou `5` secondes au lieu de la limite globale).
> - Si elle timeout, engager le fallback `GET` avec un timeout plus permissif (ex: `15` secondes). Cela libère les workers beaucoup plus vite pour les domaines en "blackhole".

### 4. Ramasse-miettes (Garbage Collection) sur le Parser HTML
> **Le problème :** L'approche par `DOMDocument` est hyper sécurisée et propre (plutôt que les Regex). Mais `DOMDocument` consomme massivement de la RAM sur de gigantesques articles (parfois 50 Mo pour un article de 15 000 mots). La limite des 128 MB peut être explosée.
> **La solution :**
> - Effectuer un **Quick Check** : `if ( ! str_contains( $content, '<a ' ) && ! str_contains( $content, 'href=' ) ) { return []; }` avant de seulement songer à allouer un `DOMDocument`. Si l'article n'a pas de liens, le traitement RAM passe de "Très Lourd" à "0".

### 5. Keyset Pagination (Fin de l'OFFSET SQL)
> **Le problème :** Dans le `ScanJob`, la récupération s'appuie ou s'est généralement appuyée sur les requêtes paginées avec `OFFSET`. Sur un blog ancien de 50 000 articles, un `OFFSET 45000` est mortel en termes de performance I/O MySQL.
> **La solution :**
> - Stocker l'`ID du dernier post processé` dans le batch transient.
> - Requêter `WHERE ID > $last_post_id ORDER BY ID ASC LIMIT 20`. C'est littéralement 10 000 fois plus rapide sur de très gros volumes car MySQL utilise l'index primaire sans besoin de charger tous les précédents en RAM.

---

## 🔒 Considérations de Sécurité Supplémentaires

*   **Prévention SSRF avancée :** Bien que ce soit un plugin admin, interdire formellement le scan des IP privées (127.0.0.1, 192.168.x.x, AWS/GCP Metadata IP `169.254.169.254`) dans `HttpChecker`. Sur AWS, une requête HTTP non contrôlée pourrait exposer les clés serveurs si un rédacteur insère le lien magique dans un article !

## 📋 Conclusion

Actuellement, l'architecture métier est **robuste**. Mais pour "scaler" d'un modeste blog à une infrastructure massive (Magazines, Affiliation), l'implémentation de ces pistes de confort (surtout le **Self-DDoS** interne et le **Keyset Pagination**) fera la différence entre un plugin grand-public classique et un outil premium "Smart".

**Souhaitez-vous que je modifie l'implémentation pour intégrer un ou plusieurs de ces pans (en priorité le bypass HTTP des liens internes, par exemple) ?**
