<?php
/////////////////////// Config Section
require_once 'config/config.php';

/////////////////////// PHPMailer Section
require_once 'vendor/PHPMailer/PHPMailerAutoload.php';

/////////////////////// Database Section - https://github.com/ajaxray/static-pdo
require_once 'vendor/SimplePDO/Db.php';
Db::setConnectionInfo(DBNAME, DBUSER, DBPASS, DBTYPE, DBHOST);
Db::execute("SET CHARACTER SET utf8");
Db::execute("SET lc_messages = 'en_US'");

require_once 'vendor/dirkolbrich/yahoo-finance-query/src/Query/Query.php';
require_once 'vendor/dirkolbrich/yahoo-finance-query/src/Query/CurrentQuote.php';
require_once 'vendor/dirkolbrich/yahoo-finance-query/src/Query/HistoricalQuote.php';
require_once 'vendor/dirkolbrich/yahoo-finance-query/src/Query/IndexList.php';
require_once 'vendor/dirkolbrich/yahoo-finance-query/src/Query/IntraDayQuote.php';
require_once 'vendor/dirkolbrich/yahoo-finance-query/src/Query/SectorList.php';
require_once 'vendor/dirkolbrich/yahoo-finance-query/src/Query/StockInfo.php';
require_once 'vendor/dirkolbrich/yahoo-finance-query/src/Query/SymbolSuggest.php';
require_once 'vendor/dirkolbrich/yahoo-finance-query/src/Exception/MissingSymbolException.php';
require_once 'vendor/dirkolbrich/yahoo-finance-query/src/YahooFinanceQuery.php';

/////////////////////// Static class for TooTutor Online
require_once 'vendor/TooTutor/TTO.php';
require_once 'vendor/TooTutor/TTOMail.php';

/////////////////////// Restler Section
require_once 'vendor/restler.php';
use Luracast\Restler\Restler;
Defaults::$crossOriginResourceSharing = true;
Defaults::$accessControlAllowOrigin = '*';

$r = new Restler();
$r->addAPIClass('Explorer');
$r->addAuthenticationClass('Auth');
$r->addAPIClass('Stock');
$r->addAPIClass('History');
$r->addAPIClass('Analysis');
$r->addAPIClass('Test');
$r->handle();
?>