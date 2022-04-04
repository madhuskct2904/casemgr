<?php namespace App\Domain\Form;

use App\Entity\Forms;

class FormToArrayTransformer
{
    public static function getFormAsArr(Forms $form): array
    {
        return [
            'id'                     => $form->getId(),
            'name'                   => $form->getName(),
            'data'                   => $form->getData(),
            'conditionals'           => $form->getConditionals(),
            'calculations'           => $form->getCalculations(),
            'hide_values'            => $form->getHideValues() ?: "[]",
            'extra_validation_rules' => $form->getExtraValidationRules() ?: "[]",
            'module_key'             => $form->getModule() ? $form->getModule()->getKey() : null,
            'share_with_participant' => $form->isSharedWithParticipant(),
            'has_shared_fields'      => $form->hasSharedFields(),
            'captcha_enabled'        => $form->isCaptchaEnabled(),
            'update_conditionals'    => $form->getUpdateConditionals()
        ];
    }
}
