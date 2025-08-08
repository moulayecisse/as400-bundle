# Installation Instructions for Your Current Project

## 1. Add Bundle to Project Dependencies

Since this is a local bundle, you need to add it to your main project's `composer.json`. Add a repository section:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./As400Bundle"
        }
    ],
    "require": {
        "cisse/as400-bundle": "*"
    }
}
```

## 2. Register the Bundle

Add to your `config/bundles.php`:

```php
return [
    // ... your existing bundles
    Cisse\Bundle\As400\As400Bundle::class => ['all' => true],
];
```

## 3. Create Configuration File

Create `config/packages/as400.yaml`:

```yaml
as400:
    connection:
        driver: '%env(DATABASE_AS400_DRIVER)%'
        system: '%env(DATABASE_AS400_SYSTEM)%'
        user: '%env(DATABASE_AS400_USER)%'
        password: '%env(DATABASE_AS400_PASSWORD)%'
        commit_mode: '%env(DATABASE_AS400_COMMIT_MODE)%'
        extended_dynamic: '%env(DATABASE_AS400_EXTENDED_DYNAMIC)%'
        package_library: '%env(DATABASE_AS400_PACKAGE_LIBRARY)%'
        translate_hex: '%env(DATABASE_AS400_TRANSLATE_HEX)%'
        database: '%env(DATABASE_AS400_DATABASE)%'
        default_libraries: '%env(DATABASE_AS400_DEFAULT_LIBRARIES)%'
```

## 4. Update Your Existing Code

### Update Import Statements

Replace your existing imports:

```php
// OLD
use App\Database\Connection\As400Connection;
use App\Repository\As400\Repository;
use App\Attribute\Entity;
use App\Attribute\Column;

// NEW
use Cisse\Bundle\As400\Database\Connection\As400Connection;
use Cisse\Bundle\As400\Repository\Repository;
use Cisse\Bundle\As400\Attribute\Entity;
use Cisse\Bundle\As400\Attribute\Column;
```

### Update Repository Classes

Your existing repositories like `CompteuRepository` need to extend the new base class:

```php
<?php
namespace App\Repository\As400\FNIF;

use App\Entity\As400\FNIF\Compteu;
use Cisse\Bundle\As400\Repository\Repository;

class CompteuRepository extends Repository
{
    protected const string ENTITY_CLASS = Compteu::class;
}
```

### Update Entity Classes

Update your existing entity classes to use the new attributes:

```php
<?php
namespace App\Entity\As400\FNIF;

use Cisse\Bundle\As400\Attribute\Column;
use Cisse\Bundle\As400\Attribute\Entity;

#[Entity(table: 'COMPTEU', identifier: 'IDCPTEUR', database: 'FNIF')]
class Compteu
{
    #[Column(name: 'IDCPTEUR')]
    public ?string $idcpteur = null;

    // ... rest of your properties
}
```

### Update Service Dependencies

If you have services using the old classes, update their constructor dependencies:

```php
<?php
namespace App\Service;

use Cisse\Bundle\As400\Database\Connection\As400Connection;

class MyService
{
    public function __construct(
        private As400Connection $connection
    ) {}
}
```

## 5. Remove Old Files (Optional)

After updating all references, you can remove the old implementation files:

- `src/Database/`
- `src/DataCollector/As400*`
- `src/Attribute/Entity.php` and `src/Attribute/Column.php`
- `src/Repository/As400/Repository.php`
- `src/Utility/As400Utility.php`
- `src/Utility/DateUtility.php`
- `src/Utility/Hydrator/`
- `src/Utility/Resolver/`
- `src/Command/As400GenerateEntityCommand.php`
- `templates/as400/`
- `templates/data_collector/as400.html.twig`

## 6. Run Composer Install

```bash
composer install
```

## 7. Clear Cache

```bash
php bin/console cache:clear
```

## 8. Test the Integration

Create a simple test to ensure everything works:

```php
<?php
// In a controller or command
public function test(As400Connection $connection)
{
    // Test connection
    if ($connection->isConnected()) {
        echo "AS400 connection successful!";
    }

    // Test a simple query
    $result = $connection->fetchAll('SELECT * FROM SYSIBM.SYSDUMMY1');
    var_dump($result);
}
```

## Notes

- The bundle maintains backward compatibility with your existing code structure
- All environment variables remain the same
- The Web Profiler integration will automatically show AS400 queries
- You can now use the `as400:generate:entity` command with the new bundle
