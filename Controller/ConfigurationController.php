<?php

namespace ShoppingFeed\Controller;

use ShoppingFeed\Model\ShoppingfeedFeedQuery;
use ShoppingFeed\Model\ShoppingfeedLogQuery;
use ShoppingFeed\Model\ShoppingfeedMappingDeliveryQuery;
use ShoppingFeed\Model\ShoppingfeedOrderDataQuery;
use ShoppingFeed\Service\LogService;
use ShoppingFeed\Service\MappingDeliveryService;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Model\ModuleQuery;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/module/ShoppingFeed", name="config_module")
 */
class ConfigurationController extends BaseAdminController
{
    protected LogService $logService;
    protected MappingDeliveryService $deliveryService;

    /**
     * @param LogService $logService
     * @param MappingDeliveryService $deliveryService
     */
    public function __construct(LogService $logService, MappingDeliveryService $deliveryService)
    {
        $this->logService = $logService;
        $this->deliveryService = $deliveryService;
    }


    /**
     * @Route("", name="view")
     */
    public function viewAction()
    {
        return $this->render(
            "shoppingfeed/configuration",
            [
                "feeds" => ShoppingfeedFeedQuery::create()->find(),
                "mappings" => ShoppingfeedMappingDeliveryQuery::create()->find(),
                "missingMappings" => $this->getMissingMappings(),
                'columnsDefinition' => $this->logService->defineColumnsDefinition(),
            ]
        );
    }

    public function getMissingMappings()
    {
        $missingMappings = ShoppingfeedLogQuery::create()
            ->filterByObjectType('Mapping')
            ->groupByObjectRef()
            ->find();

        $results = [];
        foreach ($missingMappings as $missingMapping) {
            $mappingDeliveryService = $this->deliveryService;
            $deliveryModuleId = $mappingDeliveryService->getDeliveryModuleIdFromCode($missingMapping->getObjectRef());
            if ($deliveryModuleId === 0) {
                $results[] = $missingMapping->getObjectRef();
            }
        }
        return $results;
    }

}