<?php

// Gestion de la récupération des données de proposition commerciale à partir de Dolibarr

class DolibarrProposal extends DolibarrObject
{

    protected static function getInvoiceClass(): string  { return DolibarrInvoice::class; }

    protected static function getProposalClass(): string { return DolibarrProposal::class; }


    public function getTotalHt()
    {
        return $this->data->total_ht ?? null;
    }

      /**
     * Crée un proposal  dans Dolibarr.
     * @return array|null Résultat de l’API ou null en cas d’échec
     */
    public function createInDolibarr(): ?object
    {
        $result =  DolibarrObject::postToDolibarr('/proposals', $this->data);

        return $result;
    }

    public function getContactsIds(): array
    {
        return  $this->data->contacts_ids;
    }



    /**
     * calcule le cout d'achat total pour la proposition
     *
     * @param string $field Le nom du champ à sommer (ex: 'total_ht', 'total_tva', 'total_ttc').
     * @return float La somme totale.
     */
    public function getCostPrice(): float
    {
        $total = 0.0;

        if (!isset($this->data->lines) || !is_array($this->data->lines)) {
            return $total;
        }

        foreach ($this->data->lines as $line) {
            if (isset($line->pa_ht) && isset($line->qty)) {
                $total += (float) $line->pa_ht * $line->qty;
            }
        }

        return $total;
    }


    /**
     * Récupère la liste des IDs de factures associées à cette proposition.
     * Attetion les linkedobjects sont sous cette forme : 
     *  "linkedObjectsIds": {
     * "order_supplier": {
     * "846": "180",
     * "847": "179"
     *},
     * "facture": {
     *  "542": "747",
     *  "811": "990"
     *}
     * }
     *
     * @return array Liste des IDs des factures. Warning this is IDs and not Ref.
     */
    public function getInvoiceIds(): array
    {
        $invoiceIds = [];

        if (isset($this->data->linkedObjectsIds->facture)) {
            foreach ($this->data->linkedObjectsIds->facture as $key => $facture) {
                if (isset($facture)) {
                    $invoiceIds[] = (string) $facture;
                }
            }
        }

        return $invoiceIds;
    }

    /** A proposal may be connected to sevevral supplier orders (see Proposal ref = 2024070) */
    public function getSupplierOrdersIds(): array
    {
        $orderSuppliers = [];

        if (isset($this->data->linkedObjectsIds->order_supplier)) {
            foreach ($this->data->linkedObjectsIds->order_supplier as $key => $orderId) {
                if (isset($orderId)) {
                    $orderSuppliers[] = (string) $orderId;
                }
            }
        }

        return $orderSuppliers;
    }

    /**
     * Récupère la liste des objets DolibarrSupplierOrder associés à cette proposition.
     *
     * @return DolibarrSupplierOrder[] Liste des objets DolibarrSupplierOrder.
     */
    public function getSupplierOrders(?string $supplierId = null): array
    {
        $supplierOrders = [];

        // Récupère les IDs des factures associées à la proposition
        $supplierOrderIds = $this->getSupplierOrdersIds();

        foreach ($supplierOrderIds as $supplierOrderId) {
            $supplierOrder = DolibarrSupplierOrder::getSupplierOrderFromID($supplierOrderId);
            if ($supplierOrder) {
                // Si aucun filtre, ou si le type correspond au filtre, on l'ajoute
                if (is_null($supplierId) || $supplierOrder->getSocId() === $supplierId) {
                    $supplierOrders[] = $supplierOrder;
                }
            } else {
                error_log("Impossible de récupérer la commande fournisseur avec l'ID : $supplierOrderId");
            }
        }

        return $supplierOrders;
    }


    /**
     * Récupère la liste des objets DolibarrInvoice associés à cette proposition.
     *
     * @return DolibarrInvoice[] Liste des objets DolibarrInvoice.
     */
    public function getInvoices(?DolibarrInvoiceType $filterType = null): array
    {
        $invoices = [];

        // Récupère les IDs des factures associées à la proposition
        $invoiceIds = $this->getInvoiceIds();

        $invoiceClass = $this->getInvoiceClass();

        foreach ($invoiceIds as $invoiceId) {
            $invoice = $invoiceClass::getInvoiceFromID($invoiceId);
            if ($invoice) {
                // Si aucun filtre, ou si le type correspond au filtre, on l'ajoute
                if (is_null($filterType) || $invoice->getType() === $filterType->value) {
                    $invoices[] = $invoice;
                }
            } else {
                error_log("Impossible de récupérer la facture avec l'ID : $invoiceId");
            }
        }

        return $invoices;
    }

    // Ajoutez d'autres getters ici selon les champs disponibles dans votre réponse API

    public static function getProposal($proposalRef, $retryCount = 3, $initialDelaySeconds = 10): ?static
    {
        $class = static::getProposalClass(); 

        $escapedRef = str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $proposalRef);
        $sqlfilters = urlencode("(t.ref:like:'{$escapedRef}')");
        $endpoint = "/proposals?contact_list=0&sqlfilters=" . $sqlfilters;
        $data = parent::fetchFromDolibarr($endpoint, $retryCount, $initialDelaySeconds);

        if (is_array($data)) {
            foreach ($data as $proposal) {
                if (is_object($proposal) && isset($proposal->ref) && $proposal->ref === $proposalRef) {
                    $proposalId = $proposal->id ?? null;
                    return $proposalId ? static::getProposalFromID((string) $proposalId, $retryCount, $initialDelaySeconds) : null;
                }
            }

            return null;
        }

        if (is_object($data) && isset($data->ref) && $data->ref === $proposalRef) {
            $proposalId = $data->id ?? null;
            return $proposalId ? static::getProposalFromID((string) $proposalId, $retryCount, $initialDelaySeconds) : null;
        }

        return null;
    }

    public static function getProposalFromID($proposalId, $retryCount = 3, $initialDelaySeconds = 10): ?static
    {
        $class = static::getProposalClass();
        $endpoint = "/proposals/" . $proposalId;
        $data = parent::fetchFromDolibarr($endpoint, $retryCount, $initialDelaySeconds);

        return $data ? new $class($data) : null;
    }


    public function getContacts()
    {
        $contacts = [];

        if (!empty($this->data->contacts_ids) && is_array($this->data->contacts_ids)) {
            foreach ($this->data->contacts_ids as $contact) {
                if (isset($contact->id) && isset($contact->code)) {
                    $contacts[] = [
                        'id' => $contact->id,  // ID du contact
                        'type' => strtoupper($contact->code) // Type du contact en majuscules (BILLING, CUSTOMER, etc.)
                    ];
                }
            }
        }

        return $contacts;
    }

    // Récupère un contact par son rôle (code) et retourne un tableau associatif avec les informations du contact
    // Le role peut etre "BILLING" ou "CUSTOMER"
    public function getContactByRole(string $role): ?array
    {
        foreach ($this->data->contacts_ids as $contact) {
            if ($contact->code === $role) {
                return [
                    'firstname' => $contact->firstname,
                    'lastname' => $contact->lastname,
                    'email' => $contact->email,
                    'role' => $contact->libelle
                ];
            }
        }
        return null; // Retourne null si aucun contact correspondant au rôle n'est trouvé
    }
}
