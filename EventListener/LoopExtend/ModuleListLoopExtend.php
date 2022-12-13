<?php

namespace ShoppingFeed\EventListener\LoopExtend;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use ShoppingFeed\ShoppingFeed;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Loop\LoopExtendsBuildModelCriteriaEvent;
use Thelia\Core\Event\Loop\LoopExtendsParseResultsEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Model\ModuleQuery;

class ModuleListLoopExtend implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::getLoopExtendsEvent(TheliaEvents::LOOP_EXTENDS_BUILD_MODEL_CRITERIA, 'module') => ['buildModelCriteria', 128],
            TheliaEvents::getLoopExtendsEvent(TheliaEvents::LOOP_EXTENDS_PARSE_RESULTS, 'module') => ['parseResults', 128],
        ];
    }

    public function buildModelCriteria(LoopExtendsBuildModelCriteriaEvent $event)
    {
        /** @var ModuleQuery $query */
        $query = $event->getModelCriteria();
        $params = $query->getParams();

        foreach ($params as $param) {
            if ($param["column"] === "type" && $param["value"] == 3) {
                $query->where('module.id != ?', ShoppingFeed::getModuleId());
            }
        }
    }

    public function parseResults(LoopExtendsParseResultsEvent $loopEvent)
    {
        $loopResult = $loopEvent->getLoopResult();
        $args = $loopEvent->getLoop()->getArgumentCollection();

        if (isset($args->get('module_type')->getValue()[0])) {
            if ($args->get('module_type')->getValue()[0] == 1) {
                $moduleShoppingFeed = ModuleQuery::create()->filterById(ShoppingFeed::getModuleId())->findOne();

                if ($moduleShoppingFeed !== null) {
                    $loopResultRow = new LoopResultRow($moduleShoppingFeed);
                    $loopResultRow
                        ->set("ID", $moduleShoppingFeed->getId())
                        ->set("IS_TRANSLATED", false)
                        ->set("LOCALE", 'fr_FR')
                        ->set("TITLE", $moduleShoppingFeed->setLocale('fr_FR')->getTitle())
                        ->set("CHAPO", $moduleShoppingFeed->setLocale('fr_FR')->getChapo())
                        ->set("DESCRIPTION", $moduleShoppingFeed->setLocale('fr_FR')->getDescription())
                        ->set("POSTSCRIPTUM", $moduleShoppingFeed->setLocale('fr_FR')->getPostscriptum())
                        ->set("CODE", $moduleShoppingFeed->getCode())
                        ->set("TYPE", $moduleShoppingFeed->getType())
                        ->set("CATEGORY", $moduleShoppingFeed->getCategory())
                        ->set("ACTIVE", $moduleShoppingFeed->getActivate())
                        ->set("VERSION", $moduleShoppingFeed->getVersion())
                        ->set("CLASS", $moduleShoppingFeed->getFullNamespace())
                        ->set("POSITION", $moduleShoppingFeed->getPosition())
                        ->set("MANDATORY", $moduleShoppingFeed->getMandatory())
                        ->set("HIDDEN", $moduleShoppingFeed->getHidden())
                        ->set("EXISTS", true);
                    $loopResult->addRow($loopResultRow);
                }
            }
        }
    }
}