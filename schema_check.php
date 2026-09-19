
require 'vendor/autoload.php';
\ = require_once 'bootstrap/app.php';
\->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
foreach (['indonesia_provinces', 'indonesia_cities', 'indonesia_districts', 'indonesia_villages'] as \) {
    echo \ . ':
';
    foreach (Schema::getColumns(\) as \) {
        echo '  ' . \['name'] . ' (' . \['type_name'] . ', nullable: ' . (\['nullable'] ? 'yes' : 'no') . ')
';
    }
}
