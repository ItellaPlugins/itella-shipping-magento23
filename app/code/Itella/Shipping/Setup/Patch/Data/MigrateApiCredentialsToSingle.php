<?php
namespace Itella\Shipping\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;

class MigrateApiCredentialsToSingle implements DataPatchInterface
{
    /** @var ScopeConfigInterface */
    private $config;

    /** @var WriterInterface */
    private $writer;

    public function __construct(
        ScopeConfigInterface $config,
        WriterInterface $writer
    ) {
        $this->config = $config;
        $this->writer = $writer;
    }

    public function apply()
    {
        $migrateMap = [
            // OLD => NEW
            'carriers/itella/user_2317' => 'carriers/itella/api_username',
            'carriers/itella/password_2317' => 'carriers/itella/api_password',
            'carriers/itella/itella_contract_2317' => 'carriers/itella/api_contract',
        ];

        $scopes = [
            ['scope' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 'scope_id' => 0],
            ['scope' => ScopeInterface::SCOPE_WEBSITES, 'scope_id' => null], // will be iterated below
            ['scope' => ScopeInterface::SCOPE_STORES, 'scope_id' => null],   // will be iterated below
        ];

        $websiteIds = $this->config->getValue('websites') ?? [];
        $storeIds = $this->config->getValue('stores') ?? [];

        foreach ($websiteIds as $id => $data) {
            $scopes[] = ['scope' => ScopeInterface::SCOPE_WEBSITES, 'scope_id' => $id];
        }

        foreach ($storeIds as $id => $data) {
            $scopes[] = ['scope' => ScopeInterface::SCOPE_STORES, 'scope_id' => $id];
        }

        foreach ($migrateMap as $oldPath => $newPath) {
            foreach ($scopes as $s) {
                $scope = $s['scope'];
                $scopeId = $s['scope_id'] ?? 0;

                $oldValue = $this->config->getValue($oldPath, $scope, $scopeId);
                $newValue = $this->config->getValue($newPath, $scope, $scopeId);

                if ($newValue === null && $oldValue !== null) {
                    $this->writer->save($newPath, $oldValue, $scope, $scopeId);
                }
            }
        }

        return $this;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
