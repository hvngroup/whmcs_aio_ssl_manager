<?php

namespace AioSSL\Core;

/**
 * Value object for cross-provider product data normalization
 */
class NormalizedProduct
{
    /** @var string Provider-specific product code */
    public $productCode;

    /** @var string Product display name */
    public $productName;

    /** @var string CA brand (Sectigo, DigiCert, etc.) */
    public $vendor;

    /** @var string 'dv','ov','ev' */
    public $validationType;

    /** @var string 'ssl','wildcard','multi_domain','code_signing','email' */
    public $productType;

    /** @var bool */
    public $supportWildcard;

    /** @var bool */
    public $supportSan;

    /** @var int */
    public $maxDomains;

    /** @var int */
    public $maxYears;

    /** @var int */
    public $minYears;

    /** @var array Pricing: ['12'=>price, '24'=>price, '36'=>price, 'san'=>['12'=>price,...]] */
    public $priceData;

    /** @var array Provider-specific metadata */
    public $extraData;

    public function __construct(array $data = [])
    {
        $this->productCode     = $data['product_code'] ?? '';
        $this->productName     = $data['product_name'] ?? '';
        $this->vendor          = $data['vendor'] ?? '';
        $this->validationType  = $data['validation_type'] ?? 'dv';
        $this->productType     = $data['product_type'] ?? 'ssl';
        $this->supportWildcard = (bool)($data['support_wildcard'] ?? false);
        $this->supportSan      = (bool)($data['support_san'] ?? false);
        $this->maxDomains      = (int)($data['max_domains'] ?? 1);
        $this->maxYears        = (int)($data['max_years'] ?? 1);
        $this->minYears        = (int)($data['min_years'] ?? 1);
        $this->priceData       = $data['price_data'] ?? [];
        $this->extraData       = $data['extra_data'] ?? [];
    }

    /**
     * Convert to database row array
     *
     * @param string $providerSlug
     * @return array
     */
    public function toDbRow(string $providerSlug): array
    {
        return [
            'provider_slug'    => $providerSlug,
            'product_code'     => $this->productCode,
            'product_name'     => $this->productName,
            'vendor'           => $this->vendor,
            'validation_type'  => $this->validationType,
            'product_type'     => $this->productType,
            'support_wildcard' => (int)$this->supportWildcard,
            'support_san'      => (int)$this->supportSan,
            'max_domains'      => $this->maxDomains,
            'max_years'        => $this->maxYears,
            'min_years'        => $this->minYears,
            'price_data'       => json_encode($this->priceData),
            'extra_data'       => json_encode($this->extraData),
            'last_sync'        => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert to plain array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'product_code'     => $this->productCode,
            'product_name'     => $this->productName,
            'vendor'           => $this->vendor,
            'validation_type'  => $this->validationType,
            'product_type'     => $this->productType,
            'support_wildcard' => (int)$this->supportWildcard,
            'support_san'      => (int)$this->supportSan,
            'max_domains'      => $this->maxDomains,
            'max_years'        => $this->maxYears,
            'min_years'        => $this->minYears,
            'price_data'       => $this->priceData,
            'extra_data'       => $this->extraData,
        ];
    }
}