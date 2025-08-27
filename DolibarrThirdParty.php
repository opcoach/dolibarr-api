<?php 

class DolibarrThirdParty extends DolibarrObject 
{
    /**
     * Récupère un tiers depuis Dolibarr par son email.
     * @return object|null
     */
    public static function getThirdPartyByEmail(string $email): ?DolibarrThirdParty
    {
        $endpoint = "/thirdparties/email/" . rawurlencode($email);
        $data = self::fetchFromDolibarr($endpoint);

        return $data ? new DolibarrThirdParty($data) : null;
  
    }

    /**
     * Récupère un tiers depuis Dolibarr par son id.
     * @return object|null
     */
    public static function getThirdPartyById(string $id): ?DolibarrThirdParty
    {
        $endpoint = "/thirdparties/" . urlencode($id);
        $data = self::fetchFromDolibarr($endpoint);
        return $data ? new DolibarrThirdParty($data) : null;
    }


     /**
     * Crée un third party dans Dolibarr.
     * @return array|null Résultat de l’API ou null en cas d’échec
     */
    public function createInDolibarr(): ?object
    {
        $result =  DolibarrObject::postToDolibarr('/thirdparties', $this->data);

        return $result;
    }


    /* ====== SETTERS DE BASE (identité / contact) ====== */
public function setName(string $name): self           { $this->set('name', $name); return $this; }
public function getName(): ?string           { return $this->get('name'); }
public function setRef(string $ref): self             { $this->set('ref', $ref); return $this; } // utile si différent de name
public function setNameAlias(?string $alias): self    { $this->set('name_alias', $alias ?? ''); return $this; }

public function setEmail(?string $email): self        { $this->set('email', $email); return $this; }
public function getEmail(): ?string        { return $this->get('email');  }
public function setPhone(?string $phone): self        { $this->set('phone', $phone); return $this; }
public function getPhone(): ?string        { return $this->get('phone'); }
public function setPhoneMobile(?string $mobile): self { $this->set('phone_mobile', $mobile); return $this; }
public function setUrl(?string $url): self            { $this->set('url', $url); return $this; }

public function setAddress(?string $addr): self       { $this->set('address', $addr); return $this; }
public function setZip(?string $zip): self            { $this->set('zip', $zip); return $this; }
public function setTown(?string $town): self          { $this->set('town', $town); return $this; }

/* ====== LOCALISATION ====== */
public function setCountryCode(string $iso2): self    { $this->set('country_code', strtoupper($iso2)); return $this; }
public function setCountryId(?string $id): self       { $this->set('country_id', $id); return $this; }
public function setStateId(?string $id): self         { $this->set('state_id', $id); return $this; }
public function setRegionId(?string $id): self        { $this->set('region_id', $id); return $this; }

/* ====== IDENTIFICATION / FISCAL ====== */
public function setIdprof1(?string $siren): self      { $this->set('idprof1', $siren ?? ''); return $this; } // FR: SIREN
public function setIdprof2(?string $siret): self      { $this->set('idprof2', $siret ?? ''); return $this; } // FR: SIRET
public function setTvaAssuj(string $assuj): self      { $this->set('tva_assuj', $assuj); return $this; }     // "1" ou "0"
public function setTvaIntra(?string $vat): self       { $this->set('tva_intra', $vat ?? ''); return $this; }

/* ====== STATUTS / TYPES / CODES ====== */
public function setStatus(string $status): self       { $this->set('status', $status); return $this; }       // "1" actif
public function setClient(string $flag): self         { $this->set('client', $flag); return $this; }          // "0".."3"
public function setFournisseur(string $flag): self    { $this->set('fournisseur', $flag); return $this; }     // "0" ou "1"
public function setCodeClient(?string $code): self    { $this->set('code_client', $code); return $this; }

public function setTypentId(?string $id): self        { $this->set('typent_id', $id); return $this; }         // ex "4"
public function setTypentCode(?string $code): self    { $this->set('typent_code', $code); return $this; }     // ex "TE_SMALL"

/* ====== RÈGLEMENTS / COMPTES / INCOTERMS ====== */
public function setCondReglementId(?string $id): self { $this->set('cond_reglement_id', $id); return $this; } // ex "9"
public function setModeReglementId(?string $id): self { $this->set('mode_reglement_id', $id); return $this; } // ex "2"
public function setFkAccount(?string $id): self       { $this->set('fk_account', $id); return $this; }        // ex "2" ou "0"
public function setFkIncoterms(?string $id): self     { $this->set('fk_incoterms', $id); return $this; }      // ex "0"
public function setSiren(?string $siren): self      
{ if ($siren !== null) {
        // supprime tous les blancs, puis coupe à 9
        $clean = substr(preg_replace('/\s+/', '', $siren), 0, 9);
        $this->set('idprof1', $clean);
    } else {
        $this->set('idprof1', '');
    }
    return $this; 
}     
public function setSiret(?string $siret): self
{
    if ($siret !== null) {
        // supprime tous les blancs, puis coupe à 14
        $clean = substr(preg_replace('/\s+/', '', $siret), 0, 14);
        $this->set('idprof2', $clean);
    } else {
        $this->set('idprof2', '');
    }

    // Can init siren with it.  
    return $this->setSiren($siret);
}




/* ====== FINANCIER ====== */
public function setAbsoluteDiscount(string $v): self   { $this->set('absolute_discount', $v); return $this; }  // "0"
public function setAbsoluteCreditnote(string $v): self { $this->set('absolute_creditnote', $v); return $this; }// "0"

/* ====== HELPERS PRATIQUES ====== */
public function asActive(): self                      { return $this->setStatus('1'); }
public function asClientOnly(): self                  { $this->setClient('1'); $this->setFournisseur('0'); return $this; }
public function asSupplierOnly(): self                { $this->setClient('0'); $this->setFournisseur('1'); return $this; }
public function asClientAndSupplier(): self           { $this->setClient('1'); $this->setFournisseur('1'); return $this; }

/* Optionnel: si tu veux que name=ref automatiquement */
public function setNameAndRef(string $name): self
{
    $this->setName($name);
    $this->setRef($name);
    return $this;
}
    
}