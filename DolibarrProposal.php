<?php

// Gestion de la récupération des données de proposition commerciale à partir de Dolibarr


// Enumération pour les certifications (attribut supplémentaire de proposition commerciale dans dolibarr)
enum Certification: string
{
    case CLOE = '2';
    case Lillate = '3';
    case Linguaskill = '4';
    case English360 = '5';
    case TOEIC = '6';
    case Pipplet = '7';
    case Bright = '8';
    case TosaPhotoshop = '9';
    case TosaExcel = '10';
    case TosaVB2019 = '11';
}


//  Enumération pour les financeurs (défini dans dolibarr, attribut supplémentaire de proposition commerciale)
enum Financeur: string
{
    case NonDefini = '1';
    case Entreprise = '10';
    case ServicePublic = '12';
    case Personnel = '15';
    case CPF = '20';
    case AFDAS = '30';
    case ATLAS = '40';
    case Uniformation = '50';
    case AKTO = '60';
    case OCAPIAT = '70';
    case OPCO2i = '80';
    case Construction = '90';
    case OPCOMobilite = '100';
    case OPCOEP = '110';
    case OPCSante = '120';
    case OPCCommerce = '130';
    case PlaceDeLaFormation = '200';
    case PoleEmploi = '300';
}

// Enumération pour les conditions de facturation (attribut supplémentaire de proposition commerciale dans dolibarr)
enum ConditionDeFacturation: string
{
    case NonDefini = '1';
    case VingtCinqPourcentDebutSoldeFin = '10';
    case CinquantePourcentDemarrageSoldeFin = '20';
    case CinquantePourcentMiParcoursSoldeFin = '25';
    case CinquantePourcentSignatureSoldeFin = '30';
    case CinquantePourcentDemarrageTrenteMiParcoursVingtFin = '40';
    case CentPourcentDebut = '45';
    case CentPourcentFin = '50';
    case Autre = '60';
}



class DolibarrProposal extends DolibarrObject
{



    public function getTotalHt()
    {
        return $this->data->total_ht ?? null;
    }


    /** Retourne le prix d'achat pour 1 h, -1 si impossible à trouver */
    public function getHourBuyPrice()
    {
        $lines = $this->data->lines;
        foreach ($lines as $line) {
            if (strpos($line->ref, 'H_') === 0) {
                return (float) $line->pa_ht;
            }
        }
        return -1;
    }


    /** Retourne le code du produit pour les heures */
    public function getHourProductID(): ?string
    {
        $lines = $this->data->lines;
        foreach ($lines as $line) {
            if (strpos($line->ref, 'H_') === 0) {
                return $line->fk_product;
            }
        }
        return null;
    }





    public function getDureeEnHeure(): ?float
    {
        // Retourne la valeur convertie en flottant si elle existe, sinon retourne null
        return isset($this->data->array_options->options_dureenh) ? (float)$this->data->array_options->options_dureenh : null;
    }

    public function getFinanceur(): ?Financeur
    {
        return Financeur::tryFrom($this->data->array_options->options_financeur ?? null);
    }

    public function getConditionDeFacturation(): ?ConditionDeFacturation
    {
        return ConditionDeFacturation::tryFrom($this->data->array_options->options_conditionsdefacturation ?? null);
    }


    // Récupère le code affaire
    public function getAffaireCode(): ?string
    {
        return $this->data->array_options->options_codeaffaire ?? null;
    }

    // Récupère le nom du formateur
    public function getTrainerName(): ?string
    {
        return $this->data->array_options->options_formateur ?? null;
    }

    // Récupère le nom des stagiaires
    public function getTraineeNames(): ?string
    {
        return $this->data->array_options->options_stagiaires ?? null;
    }

    // Récupère l'intitulé de la formation
    public function getTrainingTitle(): ?string
    {
        return $this->data->array_options->options_intituleformation ?? null;
    }

    // Récupère la thématique  
    public function getThematique(): ?string
    {
        return $this->data->array_options->options_thematique ?? null;
    }

    // Fonction pour obtenir le code de langue à partir de la thématique
    public function getLanguageId(): ?string
    {
        $thematique = $this->getThematique();

        // Tableau de correspondance thématique => code langue
        $languageMap = [
            10 => 'en', // Anglais
            20 => 'de', // Allemand
            30 => 'ar', // Arabe
            40 => 'zh', // Chinois
            50 => 'es', // Espagnol
            60 => 'fle', // Français (FLE)
            70 => 'it', // Italien
            80 => 'ja', // Japonais
            85 => 'lsf', // Langue des signes français (LSF)
            90 => 'pt', // Portugais
            100 => 'ru' // Russe
        ];

        // Retourne le code langue ou null si inconnu
        return $languageMap[$thematique] ?? null;
    }



    // Récupère le taux de sous-traitance
    public function getSubcontractingRate(): ?string
    {
        return $this->data->array_options->options_soustraitance ?? null;
    }


    public function getLevel($levelInt): ?string
    {

        $levelMapping = [
            0  => 'NonDefini',
            10 => 'A1Moins',
            11 => 'A1',
            12 => 'A1Plus',
            20 => 'A2Moins',
            21 => 'A2',
            22 => 'A2Plus',
            30 => 'DebutB1',
            31 => 'MiB1',
            32 => 'FinB1',
            40 => 'B2Moins',
            41 => 'B2',
            42 => 'B2Plus',
            50 => 'C1',
            60 => 'C2'
        ];

        return $levelMapping[$levelInt] ?? 'NonDefini';
    }

    public function getStartLevel(): ?string
    {

        $level = $this->data->array_options->options_niveaudepart ?? null;
        return $this->getLevel($level);
    }

    public function getEndLevel(): ?string
    {

        $level = $this->getOption("option_niveaufin");
        return $this->getLevel($level);
    }
    // Récupère la date de début en timestamp
    public function getStartDateTimestamp(): ?int
    {
        return isset($this->data->array_options->options_datedebut)
            ? (int) $this->data->array_options->options_datedebut
            : null;
    }

    /*$ Retourne la date sous forme de string compatible avec les entries Gravity Forms Y-m-d */
    public function getStartDate(): ?string
    {
        return $this->getFormattedDate($this->getStartDateTimestamp());
    }

    // Récupère la date de fin en timestamp
    public function getEndDateTimestamp(): ?int
    {
        return isset($this->data->array_options->options_datedefin)
            ? (int) $this->data->array_options->options_datedefin
            : null;
    }

    public function getEndDate(): ?string
    {
        return $this->getFormattedDate($this->getEndDateTimestamp());
    }

    // Récupère la durée en heures
    public function getDurationHours(): ?float
    {
        return isset($this->data->array_options->options_dureenh)
            ? (float) $this->data->array_options->options_dureenh
            : null;
    }

    // Récupère le lieu de la formation
    public function getLocation(): ?string
    {
        return $this->data->array_options->options_lieu ?? null;
    }

    // Récupère le code financeur
    public function getFinancerCode(): ?string
    {
        return $this->data->array_options->options_financeur ?? null;
    }

    // Récupère le numéro de dossier CPF
    public function getCpfFileNumber(): ?string
    {
        return $this->data->array_options->options_dossiercpf ?? null;
    }



    public function getContactsIds(): array
    {
        return  $this->data->contacts_ids;
    }


    public function getCertification(): ?Certification
    {
        return Certification::tryFrom($this->data->array_options->options_certification ?? null);
    }

    public function getDateDeDebut(): ?DateTime
    {
        return isset($this->data->array_options->options_datedebut) ? (new DateTime())->setTimestamp((int)$this->data->array_options->options_datedebut) : null;
    }

    public function getDateDeFin(): ?DateTime
    {
        return isset($this->data->array_options->options_datedefin) ? (new DateTime())->setTimestamp((int)$this->data->array_options->options_datedefin) : null;
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

    public function getElearningPlatform(): ?string
    {
        // On associe le code gravity form attendu au code produit dans dolibar (EL_....)
        $platforms = [
            'EL_7Speaking' => '7Speaking',
            'EL_CAMBRIDGE_ONLINE' => 'Cambridge',
            'EL_GS_12M' => 'GlobeSpeaker',
            'EL_GS_3M' => 'GlobeSpeaker',
            'EL_GS_6M' => 'GlobeSpeaker',
            'EL_MyCow' => 'MyCow'
        ];

        if (!isset($this->data->lines) || !is_array($this->data->lines)) {
            return null;
        }

        foreach ($this->data->lines as $line) {
            if (isset($line->ref) && str_starts_with($line->ref, 'EL_')) {
                return $platforms[$line->ref] ?? null;
            }
        }

        return "Aucun";
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

        foreach ($invoiceIds as $invoiceId) {
            $invoice = DolibarrInvoice::getInvoiceFromID($invoiceId);
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

    public static function getProposal($proposalRef, $retryCount = 3, $initialDelaySeconds = 10): ?DolibarrProposal
    {
        $endpoint = "/proposals/ref/" . $proposalRef . "?contact_list=0";
        $data = parent::fetchFromDolibarr($endpoint, $retryCount, $initialDelaySeconds);

        return $data ? new self($data) : null;
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
