<?php


namespace aminkt\yii2\payment\traits;

use aminkt\exceptions\SecurityException;
use aminkt\yii2\payment\models\TransactionInquiry;
use aminkt\yii2\payment\models\TransactionLog;
use aminkt\yii2\payment\models\TransactionSession;
use aminkt\yii2\payment\Payment;
use \yii\helpers\Html;
use aminkt\yii2\payment\components\PaymentEvent;

/**
 * Trait LogTrait
 *
 * This trait used in component `\aminkt\yii2\payment\components\Payment`.
 *
 * This trait will save payment data into database.
 *
 * @see     \aminkt\yii2\payment\components\Payment
 *
 * @package aminkt\yii2\payment\traits
 *
 * @author  Amin Keshavarz <ak_1596@yahoo.com>
 */
trait LogTrait
{

    /**
     * List of errors.
     *
     * @var array $errors
     */
    protected static $errors = [];

    /**
     * Save transaction data in db when verify request send and return true if its work correctly.
     *
     * @param \aminkt\yii2\payment\gates\AbstractGate   $gate
     *
     * @throws \aminkt\exceptions\SecurityException
     *
     * @return bool
     */
    public function saveVerifyDataIntoDatabase($gate)
    {
        /**
         * Throw an verify event. can be used in kernel to save and modify transactions.
         */
        $transactionSession = TransactionSession::findOne($gate->getOrderId());

        /**
         * Save transactions logs.
         */
        self::saveLogData($transactionSession, $gate, TransactionLog::STATUS_PAYMENT_VERIFY);

        /**
         * Check transaction correctness.
         */
        if ($transactionSession->status == TransactionSession::STATUS_PAID) {
            throw new SecurityException("This transaction paid before.");
        }

        /**
         * Update transactionSession data.
         */
        $transactionSession->user_card_hash = $gate->getCardHash();
        $transactionSession->user_card_pan = $gate->getCardPan();
        $transactionSession->tracking_code = $gate->getTrackingCode();
        if ($gate->getStatus()) {
            $transactionSession->status = TransactionSession::STATUS_PAID;
        } else {
            $transactionSession->status = TransactionSession::STATUS_FAILED;
        }

        if (!$transactionSession->save()) {
            \Yii::error($transactionSession->getErrors(), self::className());
            throw new \RuntimeException('Can not save transaction session data.', 12);
        } else {
            /**
             * Create an inquiry request for valid payments.
             */
            $inquiryRequest = new TransactionInquiry();
            $inquiryRequest->session_id = $transactionSession->id;
            $inquiryRequest->status = TransactionInquiry::STATUS_INQUIRY_WAITING;
            $inquiryRequest->save(false);
        }

        /**
         * Throw an verify event. can be used in kernel to save and modify transactions.
         */
        $event = new PaymentEvent();
        $event->setGate($gate)
            ->setStatus($gate->getStatus())
            ->setTransactionSession($transactionSession);
        \Yii::$app->trigger(\aminkt\yii2\payment\Payment::AFTER_PAYMENT_VERIFY, $event);
        return true;
    }


    /**
     * Save transaction data in db when inquiry request send and return true if its work correctly.
     *
     * @param \aminkt\yii2\payment\gates\AbstractGate       $gate
     * @param TransactionInquiry $inquiryModel
     *
     * @return bool
     */
    public function saveInquiryDataIntoDatabase($gate, $inquiryModel)
    {
        /**
         * Save transactions logs.
         */
        self::saveLogData($inquiryModel->transactionSession, $gate, TransactionLog::STATUS_PAYMENT_INQUIRY);

        if ($gate->getStatus()) {
            $inquiryModel->status = TransactionInquiry::STATUS_INQUIRY_SUCCESS;
        } else {
            $inquiryModel->status = TransactionInquiry::STATUS_INQUIRY_FAILED;
        }

        if (!$inquiryModel->save()) {
            \Yii::error($inquiryModel->getErrors(), self::className());
            throw new \RuntimeException('Can not save transaction inquiry data.', 12);
        }

        /**
         * Throw an verify event. can be used in kernel to save and modify transactions.
         */
        $event = new PaymentEvent();
        $event->setGate($gate)
            ->setStatus($gate->getStatus())
            ->setTransactionInquiry($inquiryModel)
            ->setTransactionSession($inquiryModel->transactionSession);
        \Yii::$app->trigger(Payment::BEFORE_PAYMENT_INQUIRY, $event);
        return true;
    }


    /**
     * Save payment data in db when pay request send and return true if its work correctly.
     *
     * @param \aminkt\yii2\payment\gates\AbstractGate $gate Gate object.
     * @param \aminkt\yii2\payment\interfaces\OrderInterface       $order   Order model.
     * @param string       $description
     *
     * @return TransactionSession
     */
    public function savePaymentDataIntoDatabase($gate, $order, $description)
    {
        // Create transaction session data.
        $transactionSession = new TransactionSession([
            'authority' => $gate->getAuthority(),
            'order_id' => $order->getId(),
            'psp' => $gate::className(),
            'amount' => $gate->getAmount(),
            'description' => Html::encode($description),
            'status' => TransactionSession::STATUS_NOT_PAID,
            'type' => TransactionSession::TYPE_WEB_BASE,
            'ip' => \Yii::$app->getRequest()->getUserIP()
        ]);

        if ($transactionSession->save()) {
            /**
             * Set transaction session id as payment order id.
             * Actual order id can be access from database later.
             **/
            $gate->setOrderId($transactionSession->id);

            /**
             * Save transactions logs.
             */
            self::saveLogData($transactionSession, $gate, TransactionLog::STATUS_PAYMENT_REQ);


            $event = new PaymentEvent();
            $event->setGate($gate)
                ->setStatus($gate->getStatus())
                ->setTransactionSession($transactionSession);
            \Yii::$app->trigger(\aminkt\yii2\payment\Payment::BEFORE_PAYMENT_REQUEST, $event);

            return $transactionSession;
        }

        \Yii::error($transactionSession->getErrors(), self::className());
        throw new \RuntimeException("Can not saving data into database.", 10);
    }

    /**
     * Save transactions logs.
     *
     * @param \aminkt\yii2\payment\models\TransactionSession $transactionSession
     * @param \aminkt\yii2\payment\gates\AbstractGate          $gate
     * @param string                                    $status
     *
     * @return void
     */
    public static function saveLogData($transactionSession, $gate, $status = TransactionLog::STATUS_UNKNOWN)
    {
        $log = new TransactionLog([
            'session_id' => $transactionSession->id,
            'bank_driver' => $gate::className(),
            'status' => $status,
            'request' => json_encode($gate->getRequest()),
            'response' => json_encode($gate->getResponse()),
            'ip' => \Yii::$app->getRequest()->getUserIP(),
        ]);
        $log->save(false);
    }

    /**
     * Update transaction session data.
     *
     * @param TransactionSession    $session
     * @param $col
     * @param $value
     *
     * @return TransactionSession
     *
     * @throws \RuntimeException
     */
    private function updatePaymentDataInDatabase($session, $col, $value)
    {
        $session->$col = $value;

        if ($session->save()) {
            return $session;
        }

        throw new \RuntimeException("Cant save data into database.");
    }
}