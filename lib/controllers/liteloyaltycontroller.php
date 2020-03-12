<?php
class LiteLoyaltyController extends LoyaltyController
{

    function getPointsEarnedFromCompleteOrder($complete_order)
    {
        $this->points_earned = 0;
        return 0;
    }

    function recordPointsAndHistory($user_id,$order_id,$process,$points)
    {
        return true;
    }

}
?>