# Dolibarr API Plugin

A lightweight and extensible WordPress plugin designed to interact with the Dolibarr REST API. It provides an object-oriented interface to retrieve and manipulate Dolibarr entities such as proposals, invoices, products, and more.

## Features

- Connects to Dolibarr REST API using API key authentication
- Object-oriented PHP classes for main entities:
  - `DolibarrProposal`
  - `DolibarrInvoice`
  - `DolibarrProduct`
  - `DolibarrSupplierOrder`
  - `DolibarrSupplierInvoice`
  - `DolibarrEvent`
- Includes base class `DolibarrObject` for shared behavior
- Can be easily extended (e.g., `OPCoachProposal` inherits `DolibarrProposal`)

## Requirements

- PHP 8.1 or higher
- WordPress 6.8.2 or higher
- A working Dolibarr instance with REST API enabled
- Valid Dolibarr API key

## Installation

1. Clone this repository into your WordPress `wp-content/plugins/` directory:

```bash
git clone https://github.com/yourusername/dolibarr-api.git wp-content/plugins/dolibarr-api
```

2. Activate the plugin from the WordPress admin panel.

3. Define the required constants in your `wp-config.php` or a site-specific plugin:

```php
define('DOLIBARR_API_KEY', 'your_api_key_here');
define('DOLIBARR_REST_URL', 'https://your.dolibarr.instance/api/index.php');
define('DOLIBARR_DOCUMENT_URL', 'https://your.dolibarr.instance/viewfile.php');
```

## Usage

Use the provided classes in your plugin or theme to access Dolibarr data. Example:

```php
$proposal = DolibarrProposal::getProposal('PR12345');
if ($proposal) {
    echo 'Client ID: ' . $proposal->getSocid();
}
```

You can extend the base classes for customization. For example:

```php
class MyProposal extends DolibarrProposal {

    // Mus override this method to get MyProposal instances when calling ancestor methods.
   protected static function getProposalClass(): string
   {
        return MyProposal::class;
   }

    public function getFinanceur(): ?string {
        return $this->data->financeur ?? null;
    }
}
```

then call MyProposal::getProposal('XXXXX') to get  instances of MyProposal. 

## Extensibility

Each base class can be extended to suit your needs. For instance, override `getProposalClass()` in your custom proposal class to return your subclass.

```php
protected static function getProposalClass(): string {
    return self::class;
}
```

## License

This project is licensed under the Eclipse Public License 2.0 (EPL-2.0).  
See the [LICENSE](https://www.eclipse.org/legal/epl-2.0/) for details.
