<?php

namespace App\Libraries;

use Sharp\Models\CompanyProfileModel;

class EmailDelivery
{
    /**
     * @param int $companyID
     *
     * @return bool Whether this send needs to be through Custom SMTP.
     */
    public static function useCustomSmtp(int $companyID): bool
    {
        $offering = CompanyProfileModel::where('id', $companyID)->pluck('productOffering');
        $isCrm = (new Offering($offering))->isCrm();
        $forceCustomSmtp = false; //$this->isCustomSmtpForced($companyID);

        // FreeCRM always requires Custom SMTP.
        return $isCrm || $forceCustomSmtp;
    }
}
