<?php
/*
 * @desc This is actually ONLY the MessagerControllerFactory.  it was named incorrectly.
 * @author radamnyc
 */
final Class ImporterFactory
{

    /**
     *
     * Will return the required importer based on the url
     * @param $url
     *
     * @return SplickitImporter
     */
    public static function getImporterFromUrl($url)
    {
        if (preg_match('%/import/([A-Za-z]+)/%', $url, $matches)) {
            $importer_name = ucfirst($matches[1]).'Importer';
            if (file_exists("lib".DIRECTORY_SEPARATOR."importers".DIRECTORY_SEPARATOR.strtolower($importer_name).".php")) {
                require_once "lib".DIRECTORY_SEPARATOR."importers".DIRECTORY_SEPARATOR.strtolower($importer_name).".php";
                myerror_log("we have the importer name: $importer_name");
                return new $importer_name($url);
            }
            throw new NoMatchingImporterException($importer_name);
        }
    }
}
class NoMatchingImporterException extends Exception
{
    public function __construct($importer_name) {
        parent::__construct("No matching importer for: $importer_name", 100);
    }
}

?>
