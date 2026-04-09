<?php

/* Gestion commune pour les objets récupérés depuis Dolibarr */
abstract class DolibarrObject
{
    protected $data;

    public function __construct($data = null)
    {
        if (is_object($data)) {
            $this->data = $data;
        } else {
            $this->data = new stdClass();
        }
    }

    /**
     * Définit un champ dans la structure de données à envoyer à Dolibarr.
     *
     * @param string $key Nom du champ
     * @param mixed $value Valeur du champ
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->data->$key = $value;
    }

    // Méthodes get générique pour un champ (retourne la valeur string ou null si pas défini)
    public function get(string $key): ?string
    {
        return $this->data->$key ?? null;
    }


    // Méthodes get pour les option
    public function getOption(string $optionKey): ?string
    {
        return $this->data->array_options->$optionKey ?? null;
    }




    // Méthodes "getter" pour chaque champ nommé
    public function getId(): ?string
    {
        return $this->get('id');
    }

    /**
     * Récupère le `socid` associé à la proposition.
     *
     * @return int|null L'ID du client (socid) ou null si non trouvé.
     */
    public function getSocId(): ?string
    {
        return $this->get('socid');
    }

    public function getRef(): ?string
    {
        return $this->get('ref');
    }

    public function getRefExt(): ?string
    {
        return $this->get('ref_ext');
    }


    public function getStatus(): ?string
    {
        return $this->get('status');
    }

    public function getProjectId(): ?string
    {
        return $this->get('fk_project');
    }



    public static function fetchFromDolibarr($endpoint, $retryCount = 3, $initialDelaySeconds = 10)
    {
        $apiKey = DOLIBARR_API_KEY;
        $url = DOLIBARR_REST_URL . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "DOLAPIKEY: $apiKey"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Accepte toutes les encodages et favorise UTF-8

        $attempts = 0;
        $response = false;

        while ($attempts < $retryCount && !$response) {
            $response = curl_exec($ch);
            $attempts++;

            if (curl_errno($ch)) {
                error_log("Tentative $attempts : Erreur cURL : " . curl_error($ch));
                $response = false;
            } else {
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode !== 200) {
                    error_log("Tentative $attempts : Erreur HTTP : $httpCode pour l'URL : $url");
                    $response = false;

                    if ($httpCode == 429) {
                        sleep($initialDelaySeconds * $attempts);
                    }
                } else {
                    break;
                }
            }

            if (!$response && $httpCode != 429) {
                sleep($initialDelaySeconds);
            }
        }

        curl_close($ch);

        if (!$response) {
            error_log("Échec de récupération des données après $retryCount tentatives.");
            return null;
        }

        // Nettoyage non destructif uniquement.
        // Important: ne pas utiliser stripslashes() ici, car cela casse les
        // séquences JSON valides comme \u00e9 ou les guillemets échappés.
        $response = trim($response);
        $response = preg_replace('/^\xEF\xBB\xBF/', '', $response);

        $data = json_decode($response, false, 512, JSON_INVALID_UTF8_SUBSTITUTE);

        if (json_last_error() === JSON_ERROR_NONE) {
            // Certains retours Dolibarr peuvent être double-encodés:
            // json_decode() renvoie alors une chaîne contenant du JSON.
            if (is_string($data)) {
                $maybeJson = trim($data);
                if ($maybeJson !== '' && ($maybeJson[0] === '[' || $maybeJson[0] === '{')) {
                    $decodedAgain = json_decode($maybeJson, false, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decodedAgain;
                    }
                }
            }

            return $data;
        }

        error_log('Erreur de décodage JSON : ' . json_last_error_msg());
        error_log('Réponse brute Dolibarr (aperçu) : ' . mb_substr($response, 0, 1000));
        return null;
    }


   private static function sendToDolibarr(string $method, string $endpoint, $payload, int $retryCount = 3, int $initialDelaySeconds = 10, array $extraHeaders = []): ?object
{
    $apiKey = DOLIBARR_API_KEY;
    $url = rtrim(DOLIBARR_REST_URL, '/') . $endpoint;

    $jsonPayload = json_encode(is_object($payload) ? (array)$payload : $payload, JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // <-- pour séparer header/body
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    $headers = array_merge([
        "DOLAPIKEY: $apiKey",
        "Content-Type: application/json",
        "Accept: application/json"
    ], $extraHeaders);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($jsonPayload !== false) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);

    $attempt = 0;
    while (true) {
        $raw = curl_exec($ch);
        $attempt++;

        if ($raw === false) {
            error_log("Tentative $method $attempt : Erreur cURL : " . curl_error($ch));
        } else {
            $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $respHeaders = substr($raw, 0, $headerSize);
            $body        = substr($raw, $headerSize);

            if ($httpCode === 200 || $httpCode === 201) {
                // Réponse OK
                $data = json_decode($body);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return is_int($data) ? (object)['result' => $data] : $data;
                }
                error_log("Réponse non-JSON ($method $httpCode) : " . substr($body, 0, 1000));
                return null;
            }

            // Log détaillé sur erreur
            $bodyPreview = trim(mb_substr($body ?? '', 0, 1000));
            error_log("Erreur HTTP $httpCode $method $url (tentative $attempt). Body: " . ($bodyPreview !== '' ? $bodyPreview : '[vide]'));

            // Politique de retry :
            // - 429 et 5xx : on retente
            // - 4xx (sauf 429) : ne pas retenter (erreur côté client/config)
            if ($httpCode == 429 || ($httpCode >= 500 && $httpCode <= 599)) {
                if ($attempt < $retryCount) {
                    sleep($initialDelaySeconds * $attempt);
                    continue;
                }
            }
            // 4xx non-429 → stop net (ex: 401/403, liste d'IP, droits, payload invalide, etc.)
            break;
        }

        if ($attempt >= $retryCount) {
            break;
        }
        sleep($initialDelaySeconds);
    }

    // Derniers détails utiles pour debug
    $reqHeaders = curl_getinfo($ch, CURLINFO_HEADER_OUT);
    if ($reqHeaders) {
        error_log("Headers requête envoyés:\n$reqHeaders");
    }

    curl_close($ch);
    error_log("Échec de $method vers Dolibarr après $attempt tentative(s).");
    return null;
}

    /**
     * Effectue une requête POST vers l'API REST de Dolibarr.
     *
     * @param string $endpoint Endpoint REST (ex: /supplierinvoices)
     * @param mixed $payload Données à envoyer (tableau ou objet)
     * @param int $retryCount Nombre de tentatives en cas d’échec
     * @param int $initialDelaySeconds Temps d’attente entre les tentatives
     * @return object|null Réponse JSON décodée sous forme d’objet, ou null en cas d’erreur
     */

    public static function postToDolibarr($endpoint, $payload, $retryCount = 3, $initialDelaySeconds = 10): ?object
    {
        return self::sendToDolibarr('POST', $endpoint, $payload, $retryCount, $initialDelaySeconds);
    }

    public static function putToDolibarr($endpoint, $payload, $retryCount = 3, $initialDelaySeconds = 10): ?object
    {
        return self::sendToDolibarr('PUT', $endpoint, $payload, $retryCount, $initialDelaySeconds);
    }

    public static function deleteToDolibarr($endpoint, $retryCount = 3, $initialDelaySeconds = 10): ?object
    {
        return self::sendToDolibarr('DELETE', $endpoint, [], $retryCount, $initialDelaySeconds);
    }

 
        public static function ping(string $endpoint = '/status'): bool
    {
        // Appel "léger" : 1 seule tentative, 1s d’attente si besoin.
        // On suppose que /status renvoie 200 si OK (Dolibarr REST module).
        $data = self::fetchFromDolibarr($endpoint, /* retryCount */ 1, /* initialDelaySeconds */ 1);

        // fetchFromDolibarr() retourne null si HTTP≠200 ou JSON invalide
        return $data !== null;
    }



    /**
     * Crée un objet dans Dolibarr.
     *
     * Must be implemented in child classes.
     */
    public function createInDolibarr(): ?object
    {
       return null;
    }



    public function printData()
    {
        // Vérifie si $this->data est un objet ou un tableau et le convertit en JSON formaté
        $formattedData = json_encode($this->data, JSON_PRETTY_PRINT);
        echo "<pre>" . $formattedData . "</pre>";
    }

    public function getLines(): ?array
    {
        return $this->data->lines ?? null;
    }

    /**
     * Ajoute une ligne dans le tableau data->lines de l'objet courant.
     *
     * @param array $line Ligne de facture Dolibarr formatée
     * @return void
     */
    public function addLine(array $line): void
    {
        if (!isset($this->data->lines) || !is_array($this->data->lines)) {
            $this->data->lines = [];
        }

        $this->data->lines[] = $line;
    }



    /**
     * Convertit un timestamp UNIX en une date formatée (Y-m-d), avec gestion du fuseau horaire.
     *
     * @param int|string|null $timestamp Le timestamp UNIX ou une date sous forme de chaîne.
     * @param string $timezone Le fuseau horaire (par défaut 'Europe/Paris').
     * @return string|null La date formatée ou null si la valeur est invalide.
     */
    protected function getFormattedDate($timestamp, $timezone = 'Europe/Paris'): ?string
    {
        if (empty($timestamp)) {
            return null; // Si la date est vide ou nulle
        }

        try {
            // Si c'est un timestamp numérique, on le convertit
            if (is_numeric($timestamp)) {
                $date = new DateTime("@$timestamp"); // Le '@' indique qu'il s'agit d'un timestamp UNIX
            } else {
                $date = new DateTime($timestamp);    // Cas d'une date au format texte
            }

            // Ajustement du fuseau horaire
            $date->setTimezone(new DateTimeZone($timezone));

            // Formatage final
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            error_log('Erreur de conversion de date : ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère la liste des fichiers associés à un module Dolibarr pour une référence donnée.
     *
     * @param string $module Le module Dolibarr (ex: 'facture', 'projet', etc.).
     * @param string $ref    La référence de l'objet Dolibarr (ex: ID de la facture).
     *
     * @return array Liste des noms de fichiers (filtrés par extension si nécessaire).
     */
    public function getFilenames(string $module, string $ref): array
    {
        // Construction de l'endpoint pour récupérer les documents liés à un module et une référence donnée
        $endpoint = "/documents/?modulepart=" . urlencode($module) . "&ref=" . urlencode($ref);

        // Appel à l'API Dolibarr pour récupérer les données des documents
        $data = self::fetchFromDolibarr($endpoint);

        // Initialisation du tableau des noms de fichiers
        $filenames = [];

        // Vérification de la validité des données reçues
        if (is_array($data)) {
            foreach ($data as $file) {
                if (isset($file->filename)) {
                    $filenames[] = $file->filename;
                }
            }
        }

        return $filenames;
    }


    
}
