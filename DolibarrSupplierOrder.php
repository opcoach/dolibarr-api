<?php

// Gestion de la récupération des données de commande fournisseur à partir de Dolibarr

class DolibarrSupplierOrder extends DolibarrObject {
   
   
  
  // Retourne une facture à partir de son ID (l'id n'est pas la ref)
  public static function getSupplierOrderFromID($orderId, $retryCount = 3, $initialDelaySeconds = 10): ?DolibarrSupplierOrder
  {
      $endpoint = "/supplierorders/" . $orderId;
      $data = parent::fetchFromDolibarr($endpoint, $retryCount, $initialDelaySeconds);

      return $data ? new self($data) : null;
  }

  

    
}
