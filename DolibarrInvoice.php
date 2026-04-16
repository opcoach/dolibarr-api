<?php

// Gestion de la récupération des données de facturation à partir de Dolibarr


// Enumération pour les certifications (attribut supplémentaire de proposition commerciale dans dolibarr)
enum DolibarrInvoiceType: string {
    case BALANCE = '0';
    case SITUATION = '1';
    case ADVANCE = '3';

}

class DolibarrInvoice extends DolibarrObject {


    protected static function getInvoiceClass(): string
    {
        return DolibarrInvoice::class;
    }



    public function getTotalHt() {
        return $this->data->total_ht ?? null;
    }

    // Is this facture a balance or an advance ?
    public function getType() {
        return $this->data->type ?? null;
    }

    public function getDate() {
        $dateNum = $this->data->date ?? null;
        return $this->getFormattedDate($dateNum); // Appel de la fonction générique
    }

    public function  isAdvance() : bool {
        return $this->getType() === DolibarrInvoiceType::ADVANCE;
    }

    public function  isBalance() : bool {
        return $this->getType() === DolibarrInvoiceType::BALANCE;
    }


    public function getPdfDocumentURL(): ?string
    {
        $filenames = $this->getFilenames("invoice", $this->getRef());

        // Recherche du premier fichier se terminant par '.pdf'
        foreach ($filenames as $filename) {
            if (isset($filename) && str_ends_with(strtolower($filename), '.pdf')) {
                // Encodage du nom de fichier pour l'URL
                $encodedFilename = rawurlencode($this->getRef() . "/" . $filename);

                // Génération de l'URL sécurisée
                return DOLIBARR_DOCUMENT_URL . "?modulepart=invoice&file=" . $encodedFilename . "&entity=1";
            }
        }

        return null;
    }

    /**
     * Récupère les IDs de propositions liées à cette facture.
     *
     * @return array<int, string>
     */
    public function getProposalIds(): array
    {
        $proposalIds = $this->extractProposalIdsFromLinkedObjects();
        if ($proposalIds !== []) {
            return $proposalIds;
        }

        $invoiceId = $this->getId();
        if ($invoiceId === null || $invoiceId === '') {
            return [];
        }

        $fullInvoice = static::getInvoiceFromID($invoiceId);
        if (!$fullInvoice) {
            return [];
        }

        return $fullInvoice->extractProposalIdsFromLinkedObjects();
    }

    /**
     * Extrait les IDs de propositions depuis linkedObjectsIds sans requête complémentaire.
     *
     * @return array<int, string>
     */
    private function extractProposalIdsFromLinkedObjects(): array
    {
        $proposalIds = $this->getLinkedObjectIds('proposal');
        if ($proposalIds !== []) {
            return $proposalIds;
        }

        return $this->getLinkedObjectIds('propal');
    }

    /**
     * Récupère la ref de la proposition liée à cette facture.
     *
     * @return string|null
     */
    public function getProposalRef(): ?string
    {
        if (!class_exists('DolibarrProposal')) {
            return null;
        }

        foreach ($this->getProposalIds() as $proposalId) {
            $proposal = DolibarrProposal::getProposalFromID($proposalId);
            if ($proposal) {
                $proposalRef = trim((string) $proposal->getRef());
                if ($proposalRef !== '') {
                    return $proposalRef;
                }
            }
        }

        return null;
    }
    
    // Ajoutez d'autres getters ici selon les champs disponibles dans votre réponse API
    public static function getInvoice($invoiceRef, $retryCount = 3, $initialDelaySeconds = 10): ?static
    {
        $escapedRef = str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $invoiceRef);
        $sqlfilters = urlencode("(t.ref:like:'{$escapedRef}')");
        $endpoint = "/invoices?contact_list=1&sqlfilters=" . $sqlfilters;
        $data = parent::fetchFromDolibarr($endpoint, $retryCount, $initialDelaySeconds);

        if (is_array($data)) {
            foreach ($data as $invoice) {
                if (is_object($invoice) && isset($invoice->ref) && $invoice->ref === $invoiceRef) {
                    $invoiceClass = static::getInvoiceClass();
                    return new $invoiceClass($invoice);
                }
            }

            return null;
        }

        if (is_object($data) && isset($data->ref) && $data->ref === $invoiceRef) {
            $invoiceClass = static::getInvoiceClass();
            return new $invoiceClass($data);
        }

        return null;
    }
  
  // Retourne une facture à partir de son ID (l'id n'est pas la ref)
  public static function getInvoiceFromID($invoiceId, $retryCount = 3, $initialDelaySeconds = 10): ?static
  {
      $endpoint = "/invoices/" . $invoiceId;
      $data = parent::fetchFromDolibarr($endpoint, $retryCount, $initialDelaySeconds);

    $invoiceClass = static::getInvoiceClass();

    if (is_array($data)) {
        foreach ($data as $invoice) {
            if (!is_object($invoice)) {
                continue;
            }

            $currentId = (string) ($invoice->id ?? $invoice->rowid ?? '');
            if ($currentId !== '' && $currentId === (string) $invoiceId) {
                return new $invoiceClass($invoice);
            }
        }

        $firstInvoice = reset($data);
        return is_object($firstInvoice) ? new $invoiceClass($firstInvoice) : null;
    }

    return $data ? new $invoiceClass($data) : null;  }

    
}
