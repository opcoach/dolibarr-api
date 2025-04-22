<?php

class DolibarrSupplierInvoice extends DolibarrObject
{

    /**
     * Constructeur : initialise les données par défaut de la facture.
     *
     * @param object|null $data Données initiales éventuelles (non utilisé ici)
     */
    public function __construct(?object $data = null)
    {
        parent::__construct($data);

        $dateEcheance = (new DateTime())->add(new DateInterval('P30D'))->format('Y-m-d');
        $currentDate = (new DateTime())->format('Y-m-d');

        $this->data = new stdClass();
        $this->data->type = 0; // 0 = Standard, 2 = Acompte
        $this->data->date = $currentDate;
        $this->data->date_echeance = $dateEcheance;
        $this->data->mode_reglement_id = '2'; // Virement
        $this->data->cond_reglement_id = '16'; // À réception
        $this->data->multicurrency_code = 'EUR';
        $this->data->multicurrency_tx = '1.00000000';
        $this->data->cond_reglement_code = 'RECEP';
        $this->data->cond_reglement_doc = 'Règlement à réception';
        $this->data->mode_reglement_code = 'VIR';
        $this->data->fk_account = '1'; // ID du compte opcoach
    }


    /**
     * Ajoute une ligne de facture fournisseur.
     *
     * @param string $desc Description de la ligne
     * @param float $qty Quantité
     * @param float $unitprice Prix unitaire HT
     * @param float $tva Taux de TVA (ex: 20.0)
     * @param int|null $fk_product ID produit (optionnel)
     * @return void
     */
    public function addSupplierLine(string $desc, float $qty, float $unitprice, float $tva = 0.0, ?int $fk_product = null): void
    {
        $line = [
            'desc'     => $desc,
            'qty'      => $qty,
            'subprice' => $unitprice,
            'tva_tx'   => $tva,
            'total_ht' => round($qty * $unitprice, 2),
        ];

        if ($fk_product !== null) {
            $line['fk_product'] = $fk_product;
        }

        parent::addLine($line);
    }

    /**
     * Crée une facture fournisseur dans Dolibarr.
     *
     * @param int $supplier_id ID du fournisseur dans Dolibarr
     * @param string $label Libellé général de la facture
     * @param string $date Date de la facture (YYYY-MM-DD)
     * @param int|null $fk_project ID du projet Dolibarr (optionnel)
     * @return array|null Résultat de l’API ou null en cas d’échec
     */
    public function createInDolibarr(): ?object
    {

        $result =  DolibarrObject::postToDolibarr('/supplierinvoices?nodoc=1', $this->data);

        return $result;
    }


    /**
     * Valide une facture fournisseur après sa création.
     *
     * @param int $invoiceId ID de la facture à valider
     * @return object|null Résultat de l’appel API
     */
    public static function validateInvoice(string $invoiceId): ?object
    {
        return self::postToDolibarr("/supplierinvoices/$invoiceId/validate", []);
    }

    public static function requestPayment(string $invoiceId, ?string $date = null): bool
    {
        $endpoint = "/supplierinvoices/{$invoiceId}/payments";
        // Si une date est fournie, on la convertit en timestamp, sinon on prend la date actuelle
        if ($date) {
            $datePaiement = DateTime::createFromFormat('Y-m-d', $date)->getTimestamp();
        } else {
            $datePaiement = time();  // Timestamp actuel
        }

        // Paramètres requis
        $data = [
            'datepaye'         => $datePaiement,
            'payment_mode_id'  => 2,               // Exemple : 2 = Virement (à ajuster si besoin)
            'closepaidinvoices' => 'yes',           // On clôture la facture après paiement
            'accountid'        => 1,               // ID du compte bancaire 
            'comment'          => 'Paiement automatique via API',
        ];

        // Appel API POST
        $response = self::postToDolibarr($endpoint, $data);

        if (is_object($response) && isset($response->result) && is_numeric($response->result)) {
            return true;
        }

        error_log("❌ Échec de la demande de paiement pour la facture ID $invoiceId : " . print_r($response, true));
        return false;
    }
}
