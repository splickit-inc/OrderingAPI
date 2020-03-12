<?php

final class LoyaltyControllerFactory
{

    var $brand_id;

	/**
	 * 
	 * @desc Used to get the loyalty controller for the brand if brand loyalty is turned on. returns the custom loyalty controller if it exists, base loyalty controller if does not. returns null if brand loyalty is not on.
	 * @return LoyaltyController
	 */
    public static function getLoyaltyControllerForContext($user)
    {
        if (isBrandLoyaltyOn()) {
            myerror_logging(3,getSkinNameForContext()." brand loyalty is ON");
            $name = getIdentifierNameFromContext();
            $loyalty_factory = new LoyaltyControllerFactory();
            $loyalty_factory->brand_id = getBrandIdFromCurrentContext();
            return $loyalty_factory->getLoyaltyControllerFromSkinName($name,$user);
        } else {
            myerror_log("brand loyalty is not on");
        }
    }

    public static function getLoyaltyControllerForBrandAndSkin($user,$brand_record,$skin)
    {
        if (isBrandLoyaltyOnByBrandRecord($brand_record)) {
            myerror_log("brand loyalty is ON",3);
            $name = getNameFromSkinExternalIdentifier($skin['external_identifier']);
            $loyalty_factory = new LoyaltyControllerFactory();
            $loyalty_factory->brand_id = $brand_record['brand_id'];
            return $loyalty_factory->getLoyaltyControllerFromSkinName($name,$user);
        } else {
            myerror_log("brand loyalty is NOT on");
        }
    }

    public function getLoyaltyControllerFromSkinName($name,$user)
    {
        // now check to see if this skin has a custom loyalty controller
        $name = ucfirst($name);
        $controller_name = $name."LoyaltyController";
        $controller_name_lower = strtolower($controller_name);

        include_once "lib".DIRECTORY_SEPARATOR."controllers".DIRECTORY_SEPARATOR.$controller_name_lower.".php";

        if ($user == null && isset($_SERVER['AUTHENTICATED_USER'])) {
            $user = $_SERVER['AUTHENTICATED_USER'];
        }

        // Check to see whether the include declared the class
        if (! class_exists($controller_name, false)) {
            $controller_name =  $_SERVER['BRAND']['use_loyalty_lite'] == 1 ? 'LiteLoyaltyController' : 'HomeGrownLoyaltyController';
        }
        $resource = new Resource();
        if ($this->brand_id > 0) {
            $resource->set("data",['brand_id'=>$this->brand_id]);
        }
        $class = new $controller_name(getM(), $user, $resource);
        myerror_log("we obtained the loyalty controller: ".get_class($class));
        return $class;
    }

}
?>