<?php
/**
 * NicSRS SSL Database Operations
 * 
 * Handles all database operations for SSL orders
 * 
 * @package    WHMCS
 * @author     HVN GROUP
 * @version    2.0.0
 */

namespace nicsrsSSL;

use WHMCS\Database\Capsule;
use Exception;

class nicsrsSSLSql
{
    /**
     * Table name
     */
    const TABLE_NAME = 'nicsrs_sslorders';

    /**
     * Get SSL order by service ID
     * 
     * @param int $serviceId WHMCS service ID
     * @return object|null Order object or null
     */
    public static function GetSSLProduct($serviceId)
    {
        try {
            return Capsule::table(self::TABLE_NAME)
                ->where('serviceid', $serviceId)
                ->first();
        } catch (Exception $e) {
            logModuleCall(
                'nicsrs_ssl',
                'GetSSLProduct',
                ['serviceid' => $serviceId],
                $e->getMessage(),
                ''
            );
            return null;
        }
    }

    /**
     * Get SSL order by order ID
     * 
     * @param int $orderId Order ID
     * @return object|null Order object or null
     */
    public static function GetOrderById($orderId)
    {
        try {
            return Capsule::table(self::TABLE_NAME)
                ->where('id', $orderId)
                ->first();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get SSL order by certificate ID (remoteid)
     * 
     * @param string $certId Certificate ID
     * @return object|null Order object or null
     */
    public static function GetOrderByCertId($certId)
    {
        try {
            return Capsule::table(self::TABLE_NAME)
                ->where('remoteid', $certId)
                ->first();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get order ID by service ID
     * 
     * @param int $serviceId WHMCS service ID
     * @return int|null Order ID or null
     */
    public static function GetOrderIdByServiceId($serviceId)
    {
        $order = self::GetSSLProduct($serviceId);
        return $order ? $order->id : null;
    }

    /**
     * Create new SSL order
     * 
     * @param array $data Order data
     * @return int|false Order ID or false on failure
     */
    public static function CreateOrder(array $data)
    {
        try {
            // Set defaults
            $orderData = array_merge([
                'userid' => 0,
                'serviceid' => 0,
                'addon_id' => '',
                'remoteid' => '',
                'module' => 'nicsrs_ssl',
                'certtype' => '',
                'configdata' => '{}',
                'provisiondate' => date('Y-m-d'),
                'completiondate' => '0000-00-00 00:00:00',
                'status' => 'awaiting',
            ], $data);
            
            // Ensure configdata is JSON string
            if (is_array($orderData['configdata'])) {
                $orderData['configdata'] = json_encode($orderData['configdata']);
            }
            
            return Capsule::table(self::TABLE_NAME)->insertGetId($orderData);
            
        } catch (Exception $e) {
            logModuleCall(
                'nicsrs_ssl',
                'CreateOrder',
                $data,
                $e->getMessage(),
                ''
            );
            return false;
        }
    }

    /**
     * Update order status
     * 
     * @param int $orderId Order ID
     * @param string $status New status
     * @return bool Success
     */
    public static function UpdateOrderStatus($orderId, $status)
    {
        try {
            $updateData = ['status' => $status];
            
            // Set completion date if status is complete
            if (in_array($status, ['complete', 'issued'])) {
                $updateData['completiondate'] = date('Y-m-d H:i:s');
            }
            
            Capsule::table(self::TABLE_NAME)
                ->where('id', $orderId)
                ->update($updateData);
            
            return true;
            
        } catch (Exception $e) {
            logModuleCall(
                'nicsrs_ssl',
                'UpdateOrderStatus',
                ['orderId' => $orderId, 'status' => $status],
                $e->getMessage(),
                ''
            );
            return false;
        }
    }

    /**
     * Update order remote ID (certificate ID)
     * 
     * @param int $orderId Order ID
     * @param string $remoteId Certificate ID from NicSRS
     * @return bool Success
     */
    public static function UpdateRemoteId($orderId, $remoteId)
    {
        try {
            Capsule::table(self::TABLE_NAME)
                ->where('id', $orderId)
                ->update(['remoteid' => $remoteId]);
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Update order configdata
     * 
     * @param int $orderId Order ID
     * @param array $configData Configuration data
     * @param bool $merge Merge with existing data
     * @return bool Success
     */
    public static function UpdateConfigData($orderId, array $configData, $merge = true)
    {
        try {
            if ($merge) {
                $order = self::GetOrderById($orderId);
                if ($order) {
                    $existingData = json_decode($order->configdata, true) ?: [];
                    $configData = array_merge($existingData, $configData);
                }
            }
            
            Capsule::table(self::TABLE_NAME)
                ->where('id', $orderId)
                ->update([
                    'configdata' => json_encode($configData, JSON_UNESCAPED_UNICODE),
                ]);
            
            return true;
            
        } catch (Exception $e) {
            logModuleCall(
                'nicsrs_ssl',
                'UpdateConfigData',
                ['orderId' => $orderId],
                $e->getMessage(),
                ''
            );
            return false;
        }
    }

    /**
     * Update specific field in configdata
     * 
     * @param int $orderId Order ID
     * @param string $key Config key
     * @param mixed $value Config value
     * @return bool Success
     */
    public static function UpdateConfigField($orderId, $key, $value)
    {
        return self::UpdateConfigData($orderId, [$key => $value], true);
    }

    /**
     * Update order with API response data
     * 
     * @param int $orderId Order ID
     * @param object $apiResponse API response object
     * @return bool Success
     */
    public static function UpdateFromApiResponse($orderId, $apiResponse)
    {
        try {
            $order = self::GetOrderById($orderId);
            if (!$order) {
                return false;
            }
            
            $configData = json_decode($order->configdata, true) ?: [];
            $apiData = (array) $apiResponse;
            
            // Update applyReturn section
            if (!isset($configData['applyReturn'])) {
                $configData['applyReturn'] = [];
            }
            $configData['applyReturn'] = array_merge($configData['applyReturn'], $apiData);
            $configData['lastRefresh'] = date('Y-m-d H:i:s');
            
            // Update DCV list if available
            if (!empty($apiData['dcvList'])) {
                $configData['domainInfo'] = [];
                foreach ($apiData['dcvList'] as $dcv) {
                    $dcvArray = (array) $dcv;
                    $configData['domainInfo'][] = [
                        'domainName' => $dcvArray['domainName'] ?? '',
                        'dcvMethod' => $dcvArray['dcvMethod'] ?? 'EMAIL',
                        'dcvEmail' => $dcvArray['dcvEmail'] ?? '',
                        'isVerified' => ($dcvArray['is_verify'] ?? '') === 'verified',
                        'is_verify' => $dcvArray['is_verify'] ?? '',
                    ];
                }
            }
            
            Capsule::table(self::TABLE_NAME)
                ->where('id', $orderId)
                ->update(['configdata' => json_encode($configData, JSON_UNESCAPED_UNICODE)]);
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Update certificate type
     * 
     * @param int $orderId Order ID
     * @param string $certType Certificate type code
     * @return bool Success
     */
    public static function UpdateCertType($orderId, $certType)
    {
        try {
            Capsule::table(self::TABLE_NAME)
                ->where('id', $orderId)
                ->update(['certtype' => $certType]);
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get orders by user ID
     * 
     * @param int $userId WHMCS user ID
     * @param array $filters Optional filters
     * @return array Orders
     */
    public static function GetOrdersByUserId($userId, array $filters = [])
    {
        try {
            $query = Capsule::table(self::TABLE_NAME)
                ->where('userid', $userId);
            
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (!empty($filters['limit'])) {
                $query->limit($filters['limit']);
            }
            
            return $query->orderBy('id', 'desc')->get()->toArray();
            
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get orders by status
     * 
     * @param string|array $status Status or array of statuses
     * @param int $limit Maximum results
     * @return array Orders
     */
    public static function GetOrdersByStatus($status, $limit = 100)
    {
        try {
            $query = Capsule::table(self::TABLE_NAME);
            
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
            
            return $query->limit($limit)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();
            
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Delete order
     * 
     * @param int $orderId Order ID
     * @return bool Success
     */
    public static function DeleteOrder($orderId)
    {
        try {
            Capsule::table(self::TABLE_NAME)
                ->where('id', $orderId)
                ->delete();
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if order exists for service
     * 
     * @param int $serviceId WHMCS service ID
     * @return bool
     */
    public static function OrderExists($serviceId)
    {
        return self::GetSSLProduct($serviceId) !== null;
    }

    /**
     * Get order count by status
     * 
     * @param string $status Status
     * @return int Count
     */
    public static function GetOrderCountByStatus($status)
    {
        try {
            return Capsule::table(self::TABLE_NAME)
                ->where('status', $status)
                ->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get total order count
     * 
     * @return int Count
     */
    public static function GetTotalOrderCount()
    {
        try {
            return Capsule::table(self::TABLE_NAME)->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Search orders
     * 
     * @param string $keyword Search keyword
     * @param int $limit Maximum results
     * @return array Orders
     */
    public static function SearchOrders($keyword, $limit = 50)
    {
        try {
            return Capsule::table(self::TABLE_NAME)
                ->where('remoteid', 'like', "%{$keyword}%")
                ->orWhere('certtype', 'like', "%{$keyword}%")
                ->orWhere('configdata', 'like', "%{$keyword}%")
                ->limit($limit)
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get pending orders for sync
     * 
     * @param int $limit Maximum results
     * @return array Orders
     */
    public static function GetPendingOrdersForSync($limit = 50)
    {
        try {
            return Capsule::table(self::TABLE_NAME)
                ->whereIn('status', ['pending', 'processing'])
                ->whereNotNull('remoteid')
                ->where('remoteid', '!=', '')
                ->limit($limit)
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get expiring certificates
     * 
     * @param int $days Days until expiry
     * @return array Orders
     */
    public static function GetExpiringCertificates($days = 30)
    {
        try {
            $expiryDate = date('Y-m-d', strtotime("+{$days} days"));
            
            return Capsule::table(self::TABLE_NAME)
                ->where('status', 'complete')
                ->whereRaw("JSON_EXTRACT(configdata, '$.applyReturn.endDate') <= ?", [$expiryDate])
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Bulk update order statuses
     * 
     * @param array $orderIds Order IDs
     * @param string $status New status
     * @return int Affected rows
     */
    public static function BulkUpdateStatus(array $orderIds, $status)
    {
        try {
            return Capsule::table(self::TABLE_NAME)
                ->whereIn('id', $orderIds)
                ->update(['status' => $status]);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Create orders table if not exists
     * 
     * @return bool Success
     */
    public static function CreateTableIfNotExists()
    {
        try {
            if (Capsule::schema()->hasTable(self::TABLE_NAME)) {
                return true;
            }
            
            Capsule::schema()->create(self::TABLE_NAME, function ($table) {
                $table->increments('id');
                $table->integer('userid')->unsigned();
                $table->integer('serviceid')->unsigned();
                $table->text('addon_id')->nullable();
                $table->text('remoteid')->nullable();
                $table->text('module')->nullable();
                $table->text('certtype')->nullable();
                $table->longText('configdata')->nullable();
                $table->date('provisiondate')->nullable();
                $table->dateTime('completiondate')->nullable();
                $table->text('status')->nullable();
                
                $table->index('userid');
                $table->index('serviceid');
            });
            
            return true;
            
        } catch (Exception $e) {
            logModuleCall(
                'nicsrs_ssl',
                'CreateTableIfNotExists',
                [],
                $e->getMessage(),
                ''
            );
            return false;
        }
    }

    /**
     * Get order with service details
     * 
     * @param int $orderId Order ID
     * @return object|null Order with service info
     */
    public static function GetOrderWithService($orderId)
    {
        try {
            return Capsule::table(self::TABLE_NAME . ' as o')
                ->leftJoin('tblhosting as h', 'o.serviceid', '=', 'h.id')
                ->leftJoin('tblproducts as p', 'h.packageid', '=', 'p.id')
                ->leftJoin('tblclients as c', 'o.userid', '=', 'c.id')
                ->where('o.id', $orderId)
                ->select([
                    'o.*',
                    'h.domainstatus as service_status',
                    'h.domain as service_domain',
                    'h.nextduedate',
                    'p.name as product_name',
                    'c.firstname',
                    'c.lastname',
                    'c.email as client_email',
                    'c.companyname',
                ])
                ->first();
        } catch (Exception $e) {
            return self::GetOrderById($orderId);
        }
    }

    /**
     * Get statistics
     * 
     * @return array Statistics
     */
    public static function GetStatistics()
    {
        try {
            $stats = [
                'total' => 0,
                'awaiting' => 0,
                'pending' => 0,
                'complete' => 0,
                'cancelled' => 0,
                'revoked' => 0,
                'expired' => 0,
            ];
            
            $counts = Capsule::table(self::TABLE_NAME)
                ->select('status', Capsule::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get();
            
            foreach ($counts as $row) {
                $status = strtolower($row->status);
                if (isset($stats[$status])) {
                    $stats[$status] = $row->count;
                }
                $stats['total'] += $row->count;
            }
            
            return $stats;
            
        } catch (Exception $e) {
            return [
                'total' => 0,
                'awaiting' => 0,
                'pending' => 0,
                'complete' => 0,
                'cancelled' => 0,
                'revoked' => 0,
                'expired' => 0,
            ];
        }
    }
}