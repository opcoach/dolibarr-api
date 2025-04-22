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

        // Nettoyage des caractères problématiques
        $response = mb_convert_encoding($response, 'UTF-8', 'auto');
        $response = stripslashes($response);

        $data = json_decode($response);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        } else {
            error_log('Erreur de décodage JSON : ' . json_last_error_msg());
            return null;
        }
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
        $apiKey = DOLIBARR_API_KEY;
        $url = DOLIBARR_REST_URL . $endpoint;

        $jsonPayload = json_encode(is_object($payload) ? (array)$payload : $payload);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "DOLAPIKEY: $apiKey",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $attempts = 0;
        $response = false;

        while ($attempts < $retryCount && !$response) {
            $response = curl_exec($ch);
            $attempts++;

            if (curl_errno($ch)) {
                error_log("Tentative POST $attempts : Erreur cURL : " . curl_error($ch));
                $response = false;
            } else {
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if (!in_array($httpCode, [200, 201])) {
                    error_log("Tentative POST $attempts : Erreur HTTP : $httpCode pour l'URL : $url");
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
            error_log("Échec de POST vers Dolibarr après $retryCount tentatives.");
            return null;
        }

        $response = mb_convert_encoding($response, 'UTF-8', 'auto');
        $response = stripslashes($response);

        error_log("Réponse Dolibarr brute : " . $response);
        $data = json_decode($response);

        if (json_last_error() === JSON_ERROR_NONE) {
            // Cas spécial : Dolibarr renvoie parfois un int directement
            if (is_int($data)) {
                return (object)['result' => $data]; // On encapsule l'entier dans un objet
            }
            return $data;
        } else {
            error_log('Erreur de décodage JSON dans POST : ' . json_last_error_msg());
            return null;
        }
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
