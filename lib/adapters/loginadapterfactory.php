<?php
/*
 * @desc This is will get the correct login adapter for the context
 * @author radamnyc
 */
final Class LoginAdapterFactory
{

    public static function getLoginAdapterForContext()
    {
        if ($context_name = getIdentifierNameFromContext()) {
            $class_name = ucwords($context_name) . "LoginAdapter";
            if (file_exists("lib".DIRECTORY_SEPARATOR."adapters".DIRECTORY_SEPARATOR.strtolower($class_name).".php")) {
                if ($login_adapter = new $class_name($m)) {
                    return $login_adapter;
                }
            }
        }
        return new LoginAdapter($m);
    }
}
?>
