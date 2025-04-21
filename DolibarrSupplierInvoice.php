<?php

class DolibarrSupplierInvoice extends DolibarrObject
{
    /** @var array Liste des lignes de la facture */
    private array $lines = [];

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
    public function addLine(string $desc, float $qty, float $unitprice, float $tva = 0.0, ?int $fk_product = null): void
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

        $this->lines[] = $line;
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
    public function createInvoice(string $supplier_id, string $label, string $date, ?int $fk_project = null): ?array
    {
        $data = [
            'socid' => $supplier_id,
            'label' => $label,
            'date'  => $date,
            'lines' => $this->lines,
        ];

        if ($fk_project !== null) {
            $data['fk_project'] = $fk_project;
        }

        return DolibarrObject::postToDolibarr('/supplierinvoices', $data);
    }

    /**
     * Retourne les lignes actuellement ajoutées.
     *
     * @return array
     */
    public function getLines(): array
    {
        return $this->lines;
    }
}