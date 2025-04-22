<?php 

class DolibarrProduct extends DolibarrObject 
{
    /**
     * Constructeur : si un ID est fourni, récupère le produit depuis Dolibarr.
     *
     * @param int|null $id ID du produit/service dans Dolibarr
     */
    public function __construct(?int $id = null) 
    {
        parent::__construct();

        if ($id !== null) {
            $product = self::fetchProductById($id);
            if ($product) {
                $this->data = $product;
            } else {
                error_log("❌ Produit/Service avec ID $id introuvable dans Dolibarr.");
                $this->data = null;
            }
        }
    }

    /**
     * Récupère un produit/service depuis Dolibarr par son ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function fetchProductById(int $id): ?object
    {
        $endpoint = "/products/" . $id;
        $result = self::fetchFromDolibarr($endpoint);

        if (is_object($result) && isset($result->id)) {
            return $result;
        }

        return null;
    }

    /**
     * Retourne la référence du produit/service.
     *
     * @return string|null
     */
    public function getRef(): ?string
    {
        return $this->data->ref ?? null;
    }

    /**
     * Retourne le label du produit/service.
     *
     * @return string|null
     */
    public function getLabel(): ?string
    {
        return $this->data->label ?? null;
    }

    /**
     * Retourne le prix unitaire du produit/service.
     *
     * @return float|null
     */
    public static function getPriceForSupplier(string $productID, string $supplierID): ?float
    {
        // Endpoint pour récupérer les prix d'achat liés au produit  Prix défaut = 5
        $endpoint = "/products/purchase_prices?sortfield=t.ref&sortorder=ASC&limit=100&supplier=" . urlencode($supplierID);
    
        $result = self::fetchFromDolibarr($endpoint);
    
        if (!is_object($result)) {
            error_log("❌ Erreur lors de la récupération des prix pour le produit $productID.");
            return 5.0;
        }
    
        // Vérifier que le produit existe dans la réponse
        if (!isset($result->$productID) || !is_array($result->$productID)) {
            error_log("❌ Aucun prix trouvé pour le produit ID $productID.");
            return 5.0;
        }
    
        $prices = $result->$productID;
    
        // Parcourir les prix pour trouver celui du bon fournisseur
        foreach ($prices as $priceInfo) {
            if (isset($priceInfo->fourn_id) && $priceInfo->fourn_id == $supplierID) {
                if (isset($priceInfo->fourn_price)) {
                    return floatval($priceInfo->fourn_price);
                }
            }
        }
    
        error_log("❌ Aucun prix trouvé pour le produit ID $productID chez le fournisseur ID $supplierID. on retourne 5 par défaut... ");
        return 5.0;
    }
}