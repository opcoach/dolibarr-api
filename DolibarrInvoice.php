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
    
    // Ajoutez d'autres getters ici selon les champs disponibles dans votre réponse API
    public static function getInvoice($invoiceRef, $retryCount = 3, $initialDelaySeconds = 10): ?static
    {
        $endpoint = "/invoices/ref/" . $invoiceRef;
        $data = parent::fetchFromDolibarr($endpoint, $retryCount, $initialDelaySeconds);
        $invoiceClass = static::getInvoiceClass();
        return $data ? new $invoiceClass($data) : null;
    }
  
  // Retourne une facture à partir de son ID (l'id n'est pas la ref)
  public static function getInvoiceFromID($invoiceId, $retryCount = 3, $initialDelaySeconds = 10): ?static
  {
      $endpoint = "/invoices/" . $invoiceId;
      $data = parent::fetchFromDolibarr($endpoint, $retryCount, $initialDelaySeconds);

    $invoiceClass = static::getInvoiceClass();
    return $data ? new $invoiceClass($data) : null;  }

    
}
