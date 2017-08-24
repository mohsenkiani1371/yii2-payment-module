<?php

namespace aminkt\payment\models;

use Yii;

/**
 * This is the model class for table "{{%transaction_log}}".
 *
 * @property integer $id
 * @property string $sessionId
 * @property string $bankDriver
 * @property string $status
 * @property string $request
 * @property string $response
 * @property string $responseCode
 * @property string $description
 * @property string $ip
 * @property string $time
 *
 * @property TransactionSession $transactionSession
 *
 * @author Amin Keshavarz <ak_1596@yahoo.com>
 */
class TransactionLog extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%transaction_log}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['sessionId'], 'required'],
            [['request', 'response', 'responseCode', 'description'], 'string'],
            [['time'], 'safe'],
            [['sessionId', 'bankDriver', 'status', 'ip'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'sessionId' => 'Session ID',
            'bankDriver' => 'Bank Driver',
            'status' => 'Status',
            'request' => 'Request',
            'response' => 'Response',
            'responseCode' => 'Response Code',
            'description' => 'Description',
            'ip' => 'Ip',
            'time' => 'Time',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTransactionSession()
    {
        return $this->hasOne(TransactionSession::className(), ['id' => 'sessionId']);
    }
}
