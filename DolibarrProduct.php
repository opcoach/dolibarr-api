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

    // ➕ Tu peux ajouter d'autres getters selon les besoins
}