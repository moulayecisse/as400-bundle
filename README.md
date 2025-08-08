# AS400 Bundle

A Symfony bundle for IBM AS400/DB2 database connections with ORM-like functionality using PHP attributes.

## Features

- **AS400/DB2 Connection**: Native PDO-based connection to IBM AS400 systems
- **Attribute-based Entities**: Use PHP 8+ attributes to define entity mappings
- **Repository Pattern**: Generic repository with CRUD operations
- **Query Logging**: Built-in query logging and web profiler integration
- **Entity Generation**: Command to auto-generate entities from database tables
- **Data Hydration**: Automatic object hydration from database results

## Installation

Add the bundle to your project:

```bash
composer require cisse/as400-bundle
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Cisse\Bundle\As400\As400Bundle::class => ['all' => true],
];
```

## Configuration

Configure the bundle in `config/packages/as400.yaml`:

```yaml
as400:
    connection:
        driver: "IBM i Access ODBC Driver"  # Your ODBC driver name
        system: "your-as400-system"         # AS400 system name/IP
        user: "your-username"               # AS400 username
        password: "your-password"           # AS400 password
        commit_mode: 0                      # Optional, default: 0
        extended_dynamic: 1                 # Optional, default: 1
        package_library: "QGPL"             # Optional, default: "QGPL"
        translate_hex: "1"                  # Optional, default: "1"
        database: ""                        # Optional
        default_libraries: "LIB1,LIB2"      # Optional, comma-separated
```

## Usage

### Defining Entities

```php
<?php

namespace App\Entity;

use Cisse\Bundle\As400\Attribute\Entity;
use Cisse\Bundle\As400\Attribute\Column;

#[Entity(table: 'CUSTOMERS', identifier: 'CUST_ID', database: 'MYLIB')]
class Customer
{
    #[Column(name: 'CUST_ID', type: Column::INTEGER_TYPE)]
    public ?int $id = null;

    #[Column(name: 'CUST_NAME', type: Column::STRING_TYPE)]
    public ?string $name = null;

    #[Column(name: 'CREATED_DATE', type: Column::DATE_TYPE)]
    public ?\DateTime $createdDate = null;
}
```

### Creating Repositories

```php
<?php

namespace App\Repository;

use App\Entity\Customer;
use Cisse\Bundle\As400\Repository\Repository;

class CustomerRepository extends Repository
{
    protected const string ENTITY_CLASS = Customer::class;
}
```

### Using the Repository

```php
<?php

namespace App\Controller;

use App\Repository\CustomerRepository;

class CustomerController
{
    public function __construct(
        private CustomerRepository $customerRepository
    ) {}

    public function list()
    {
        // Find all customers
        $customers = $this->customerRepository->findAll();

        // Find by criteria
        $activeCustomers = $this->customerRepository->findBy(['STATUS' => 'ACTIVE']);

        // Find one customer
        $customer = $this->customerRepository->find(123);

        // Count customers
        $count = $this->customerRepository->count(['STATUS' => 'ACTIVE']);
    }
}
```

### Generating Entities

Use the console command to generate entities from database tables:

```bash
# Generate entity from table
php bin/console as400:generate:entity MYLIB CUSTOMERS

# Generate entity with repository
php bin/console as400:generate:entity MYLIB CUSTOMERS --with-repository

# Generate with custom namespace
php bin/console as400:generate:entity MYLIB CUSTOMERS "App\\Entity\\AS400"
```

### Direct Connection Usage

```php
<?php

use Cisse\Bundle\As400\Database\Connection\As400Connection;

class MyService
{
    public function __construct(
        private As400Connection $connection
    ) {}

    public function customQuery()
    {
        // Raw queries
        $results = $this->connection->fetchAll('SELECT * FROM MYLIB.CUSTOMERS WHERE STATUS = ?', ['ACTIVE']);

        // CRUD operations
        $this->connection->insert('MYLIB.CUSTOMERS', ['NAME' => 'John Doe', 'STATUS' => 'ACTIVE']);
        $this->connection->update('MYLIB.CUSTOMERS', ['STATUS' => 'INACTIVE'], ['CUST_ID' => 123]);
        $this->connection->delete('MYLIB.CUSTOMERS', ['CUST_ID' => 123]);
    }
}
```

## Web Profiler Integration

The bundle includes a data collector for the Symfony Web Profiler toolbar, showing:
- Executed AS400 queries
- Query parameters
- Execution times

## Column Types

Available column types for attribute mapping:

- `Column::STRING_TYPE`
- `Column::INTEGER_TYPE`
- `Column::BOOLEAN_TYPE`
- `Column::FLOAT_TYPE`
- `Column::DATE_TYPE`
- `Column::DATETIME_TYPE`
- `Column::TIME_TYPE`

## Requirements

- PHP 8.2+
- Symfony 6.4+ or 7.x
- IBM i Access ODBC Driver
- PDO extension

## License

MIT
