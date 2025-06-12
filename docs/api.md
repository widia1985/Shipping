# Shipping API Documentation

## 目录
1. [认证](#认证)
2. [获取运费报价](#获取运费报价)
3. [创建运单](#创建运单)
4. [查询包裹状态](#查询包裹状态)
5. [比较多个承运商的运费](#比较多个承运商的运费)
6. [获取最便宜的运费](#获取最便宜的运费)
7. [创建退货标签](#创建退货标签)
8. [运单管理](#运单管理)
   - [获取运单列表](#获取运单列表)
   - [获取运单详情](#获取运单详情)
   - [取消运单](#取消运单)
   - [重新打印运单标签](#重新打印运单标签)
   - [重新打印商业发票](#重新打印商业发票)
   - [批量取消运单](#批量取消运单)
   - [批量重新打印运单标签](#批量重新打印运单标签)

## 认证

所有API请求都需要在请求头中包含有效的认证令牌：

```http
Authorization: Bearer <your_token>
```

## 获取运费报价

获取指定承运商的运费报价。

**请求**
```http
POST /api/shipping/rates
```

**请求参数**
```json
{
    "carrier": "fedex",  // 或 "ups"
    "account_number": "123456789",
    "shipper": {
        "contact": {
            "personName": "John Doe",
            "phoneNumber": "1234567890",
            "emailAddress": "john@example.com"
        },
        "address": {
            "streetLines": ["123 Main St"],
            "city": "New York",
            "stateOrProvinceCode": "NY",
            "postalCode": "10001",
            "countryCode": "US"
        }
    },
    "recipient": {
        "contact": {
            "personName": "Jane Smith",
            "phoneNumber": "0987654321",
            "emailAddress": "jane@example.com"
        },
        "address": {
            "streetLines": ["456 Oak Ave"],
            "city": "Los Angeles",
            "stateOrProvinceCode": "CA",
            "postalCode": "90001",
            "countryCode": "US"
        }
    },
    "package": {
        "weight": 10.5,
        "length": 12,
        "width": 8,
        "height": 6
    },
    "service_type": "GROUND"
}
```

**响应**
```json
{
    "success": true,
    "data": {
        "totalCharge": 25.99,
        "currency": "USD",
        "serviceType": "GROUND",
        "deliveryDate": "2024-03-20",
        "transitTime": "3 days"
    }
}
```

## 创建运单

创建新的运单标签。

**请求**
```http
POST /api/shipping/labels
```

**请求参数**
```json
{
    "carrier": "fedex",
    "account_number": "123456789",
    "shipper": {
        "contact": {
            "personName": "John Doe",
            "phoneNumber": "1234567890",
            "emailAddress": "john@example.com"
        },
        "address": {
            "streetLines": ["123 Main St"],
            "city": "New York",
            "stateOrProvinceCode": "NY",
            "postalCode": "10001",
            "countryCode": "US"
        }
    },
    "recipient": {
        "contact": {
            "personName": "Jane Smith",
            "phoneNumber": "0987654321",
            "emailAddress": "jane@example.com"
        },
        "address": {
            "streetLines": ["456 Oak Ave"],
            "city": "Los Angeles",
            "stateOrProvinceCode": "CA",
            "postalCode": "90001",
            "countryCode": "US"
        }
    },
    "package": {
        "weight": 10.5,
        "length": 12,
        "width": 8,
        "height": 6
    },
    "service_type": "GROUND",
    "signature_required": true,
    "signature_type": "DIRECT",
    "ship_notify": true,
    "ship_notify_email": "notify@example.com"
}
```

**响应**
```json
{
    "success": true,
    "data": {
        "tracking_number": "794698315146",
        "label_url": "https://api.fedex.com/labels/794698315146.pdf",
        "label_data": {
            "rate": 25.99,
            "service_type": "GROUND",
            "delivery_date": "2024-03-20"
        }
    }
}
```

## 查询包裹状态

查询指定运单号的包裹状态。

**请求**
```http
POST /api/shipping/track
```

**请求参数**
```json
{
    "carrier": "fedex",
    "account_number": "123456789",
    "tracking_number": "794698315146"
}
```

**响应**
```json
{
    "success": true,
    "data": {
        "tracking_number": "794698315146",
        "status": "IN_TRANSIT",
        "last_location": "MEMPHIS, TN",
        "last_update": "2024-03-18T10:30:00Z",
        "estimated_delivery": "2024-03-20",
        "events": [
            {
                "timestamp": "2024-03-18T10:30:00Z",
                "location": "MEMPHIS, TN",
                "description": "Package in transit"
            }
        ]
    }
}
```

## 比较多个承运商的运费

比较多个承运商的运费报价。

**请求**
```http
POST /api/shipping/compare-rates
```

**请求参数**
```json
{
    "carriers": [
        {
            "carrier": "fedex",
            "account_number": "123456789"
        },
        {
            "carrier": "ups",
            "account_number": "987654321"
        }
    ],
    "shipper": {
        "contact": {
            "personName": "John Doe",
            "phoneNumber": "1234567890",
            "emailAddress": "john@example.com"
        },
        "address": {
            "streetLines": ["123 Main St"],
            "city": "New York",
            "stateOrProvinceCode": "NY",
            "postalCode": "10001",
            "countryCode": "US"
        }
    },
    "recipient": {
        "contact": {
            "personName": "Jane Smith",
            "phoneNumber": "0987654321",
            "emailAddress": "jane@example.com"
        },
        "address": {
            "streetLines": ["456 Oak Ave"],
            "city": "Los Angeles",
            "stateOrProvinceCode": "CA",
            "postalCode": "90001",
            "countryCode": "US"
        }
    },
    "package": {
        "weight": 10.5,
        "length": 12,
        "width": 8,
        "height": 6
    },
    "service_type": "GROUND"
}
```

**响应**
```json
{
    "success": true,
    "data": {
        "fedex": {
            "totalCharge": 25.99,
            "currency": "USD",
            "serviceType": "GROUND",
            "deliveryDate": "2024-03-20"
        },
        "ups": {
            "totalCharge": 27.50,
            "currency": "USD",
            "serviceType": "GROUND",
            "deliveryDate": "2024-03-21"
        }
    }
}
```

## 获取最便宜的运费

获取多个承运商中最便宜的运费报价。

**请求**
```http
POST /api/shipping/cheapest-rate
```

**请求参数**
与比较多个承运商的运费相同。

**响应**
```json
{
    "success": true,
    "data": {
        "carrier": "fedex",
        "rate": {
            "totalCharge": 25.99,
            "currency": "USD",
            "serviceType": "GROUND",
            "deliveryDate": "2024-03-20"
        }
    }
}
```

## 创建退货标签

创建退货运单标签。

**请求**
```http
POST /api/shipping/return-labels
```

**请求参数**
```json
{
    "carrier": "fedex",
    "account_number": "123456789",
    "original_tracking_number": "794698315146",
    "return_reason": "Customer Return",
    "return_instructions": "Please include original packaging",
    "shipper": {
        "contact": {
            "personName": "Jane Smith",
            "phoneNumber": "0987654321",
            "emailAddress": "jane@example.com"
        },
        "address": {
            "streetLines": ["456 Oak Ave"],
            "city": "Los Angeles",
            "stateOrProvinceCode": "CA",
            "postalCode": "90001",
            "countryCode": "US"
        }
    },
    "return_address": {
        "contact": {
            "personName": "John Doe",
            "phoneNumber": "1234567890",
            "emailAddress": "john@example.com"
        },
        "address": {
            "streetLines": ["123 Main St"],
            "city": "New York",
            "stateOrProvinceCode": "NY",
            "postalCode": "10001",
            "countryCode": "US"
        }
    },
    "package": {
        "weight": 10.5,
        "length": 12,
        "width": 8,
        "height": 6
    },
    "service_type": "GROUND",
    "rma_number": "RMA123456",
    "return_authorization_number": "RA789012",
    "signature_required": true,
    "ship_notify": true,
    "ship_notify_email": "returns@example.com"
}
```

**响应**
```json
{
    "success": true,
    "data": {
        "tracking_number": "794698315147",
        "label_url": "https://api.fedex.com/labels/794698315147.pdf",
        "label_data": {
            "rate": 25.99,
            "service_type": "GROUND",
            "delivery_date": "2024-03-20"
        }
    }
}
```

## 运单管理

### 获取运单列表

获取运单列表，支持分页和过滤。

**请求**
```http
GET /api/shipping/labels?page=1&per_page=15&carrier=fedex&status=ACTIVE&date_from=2024-03-01&date_to=2024-03-31&tracking_number=794698315146&account_number=123456789
```

**响应**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "tracking_number": "794698315146",
                "carrier": "fedex",
                "status": "ACTIVE",
                "label_url": "https://api.fedex.com/labels/794698315146.pdf",
                "created_at": "2024-03-18T10:30:00Z"
            }
        ],
        "total": 100,
        "per_page": 15
    }
}
```

### 获取运单详情

获取单个运单的详细信息。

**请求**
```http
GET /api/shipping/labels/1
```

**响应**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "tracking_number": "794698315146",
        "carrier": "fedex",
        "status": "ACTIVE",
        "label_url": "https://api.fedex.com/labels/794698315146.pdf",
        "label_data": {
            "rate": 25.99,
            "service_type": "GROUND",
            "delivery_date": "2024-03-20"
        },
        "shipper_info": {
            "contact": {
                "personName": "John Doe",
                "phoneNumber": "1234567890",
                "emailAddress": "john@example.com"
            },
            "address": {
                "streetLines": ["123 Main St"],
                "city": "New York",
                "stateOrProvinceCode": "NY",
                "postalCode": "10001",
                "countryCode": "US"
            }
        },
        "recipient_info": {
            "contact": {
                "personName": "Jane Smith",
                "phoneNumber": "0987654321",
                "emailAddress": "jane@example.com"
            },
            "address": {
                "streetLines": ["456 Oak Ave"],
                "city": "Los Angeles",
                "stateOrProvinceCode": "CA",
                "postalCode": "90001",
                "countryCode": "US"
            }
        },
        "package_info": {
            "weight": 10.5,
            "length": 12,
            "width": 8,
            "height": 6
        },
        "created_at": "2024-03-18T10:30:00Z"
    }
}
```

### 取消运单

取消指定的运单。

**请求**
```http
POST /api/shipping/labels/1/cancel
```

**响应**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "tracking_number": "794698315146",
        "status": "CANCELLED",
        "cancelled_at": "2024-03-18T11:30:00Z"
    }
}
```

### 重新打印运单标签

重新打印指定运单的标签。

**请求**
```http
POST /api/shipping/labels/1/reprint
```

**响应**
```json
{
    "success": true,
    "data": {
        "label": {
            "id": 1,
            "tracking_number": "794698315146",
            "status": "ACTIVE"
        },
        "label_url": "https://api.fedex.com/labels/794698315146.pdf"
    }
}
```

### 重新打印商业发票

重新打印指定运单的商业发票（仅国际运单）。

**请求**
```http
POST /api/shipping/labels/1/reprint-invoice
```

**响应**
```json
{
    "success": true,
    "data": {
        "invoice_url": "https://api.fedex.com/invoices/794698315146.pdf"
    }
}
```

### 批量取消运单

批量取消多个运单。

**请求**
```http
POST /api/shipping/labels/bulk-cancel
```

**请求参数**
```json
{
    "label_ids": [1, 2, 3]
}
```

**响应**
```json
{
    "success": true,
    "data": {
        "success": [1, 2],
        "failed": [
            {
                "id": 3,
                "reason": "Label is not active"
            }
        ]
    }
}
```

### 批量重新打印运单标签

批量重新打印多个运单的标签。

**请求**
```http
POST /api/shipping/labels/bulk-reprint
```

**请求参数**
```json
{
    "label_ids": [1, 2, 3]
}
```

**响应**
```json
{
    "success": true,
    "data": {
        "success": [
            {
                "id": 1,
                "label_url": "https://api.fedex.com/labels/794698315146.pdf"
            },
            {
                "id": 2,
                "label_url": "https://api.fedex.com/labels/794698315147.pdf"
            }
        ],
        "failed": [
            {
                "id": 3,
                "reason": "Label is not active"
            }
        ]
    }
}
```

## 错误处理

所有API端点都会返回统一的错误格式：

```json
{
    "success": false,
    "message": "错误信息",
    "errors": {
        "field_name": [
            "错误详情"
        ]
    }
}
```

常见HTTP状态码：
- 200: 请求成功
- 400: 请求参数错误
- 401: 未认证
- 403: 无权限
- 404: 资源不存在
- 422: 验证失败
- 500: 服务器错误 