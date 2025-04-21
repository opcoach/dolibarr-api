<?php

class DolibarrSupplierInvoice extends DolibarrObject
{

    private $uniqueRef;   // A unique Ref used to ensure supplier invoice is unique
    /**
     * Constructeur : initialise les données par défaut de la facture.
     *
     * @param object|null $data Données initiales éventuelles (non utilisé ici)
     */
    public function __construct(?object $data = null)
    {
        parent::__construct($data);

        // On se donne 5j pour payer... 
        $dateEcheance = (new DateTime())->add(new DateInterval('P5D'))->getTimestamp();

        $this->data = new stdClass();
        $this->data->type = 0; // 0 = Standard, 2 = Acompte
        $this->data->date = date("Y-m-d");
        $this->data->date_lim_reglement = $dateEcheance;
        $this->data->mode_reglement_id = '2'; // Virement
        $this->data->cond_reglement_id = '16'; // À réception
        $this->data->multicurrency_code = 'EUR';
        $this->data->multicurrency_tx = '1.00000000';
        $this->data->cond_reglement_code = 'RECEP';
        $this->data->cond_reglement_doc = 'Règlement à réception';
        $this->data->mode_reglement_code = 'VIR';
        $this->data->fk_account = '1'; // ID du compte opcoach
    }

    public function setUniqueRef(string $uniqueRef): void
    {
        $this->uniqueRef = $uniqueRef;
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
    public function createInDolibarr(string $supplier_id, string $label, string $date, string $refExt, ?string $fk_project = null): ?object
    {
        $this->set('socid', $supplier_id);
        $this->set('label', $label);
        $this->set('date', $date);
        $this->set('ref_ext', $refExt);
        $this->set('ref_supplier',  $refExt . "-" . $this->uniqueRef);
    
        if ($fk_project !== null) {
            $this->set('fk_project', $fk_project);
        }

        error_log("Payload envoyé à Dolibarr : " . json_encode($this->data, JSON_PRETTY_PRINT));
        $result =  DolibarrObject::postToDolibarr('/supplierinvoices', $this->data);

         // ✅ Validation automatique si succès
        if (is_object($result) && isset($result->id)) {
            $validated = self::validateInvoice((int) $result->id);
            return $validated ?: $result;
         }

        return $result;
    }


     /**
     * Valide une facture fournisseur après sa création.
     *
     * @param int $invoiceId ID de la facture à valider
     * @return object|null Résultat de l’appel API
     */
    public static function validateInvoice(int $invoiceId): ?object
    {
        return self::postToDolibarr("/supplierinvoices/$invoiceId/validate", []);
    }
}
