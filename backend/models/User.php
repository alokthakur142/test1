<?php
namespace backend\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * User model
 *
 * @property integer $id
 * @property string $username
 * @property string $password_hash
 * @property string $password_reset_token
 * @property string $email
 * @property string $auth_key
 * @property integer $role
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $password write-only password
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_DELETED = 2;
    const STATUS_NOTACTIVE = 0;
    const STATUS_ACTIVE = 1;
    const ROLE_USER = 0;
    public $auth_key = "";
	public $newPassword = "";
    public $company;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%user}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_date', 'modified_date'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['modified_date'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['first_name', 'last_name', 'email', 'username', 'role'], 'required'],
            ['email', 'email','message'=>'Invalid email'],
            [['allow_be'], 'integer'],
            [['username','email'], 'unique','message'=>'Already exist'],
            ['status', 'default', 'value' => self::STATUS_ACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_NOTACTIVE, self::STATUS_DELETED]],
            ['photo','string'],
            ['company_id', 'default', 'value' => \yii::$app->user->identity->company_id],
            ['created_date', 'default', 'value' => date("Y-m-d H:i:s")],
            ['role', 'default', 'value' => self::ROLE_USER],
            ['created_date', 'default', 'value' => date("Y-m-d H:i:s")],
            ['role', 'in', 'range' => self::getRoleIds()],
            [['rec_notification', 'photo', 'contact_number', 'designation'], 'safe']
        ];
    }
    
	public function beforeSave($insert)
    {	
        if(!$insert)
        {
            $company_id = \yii::$app->user->identity->company_id;
            if(isset($this->photo)) {
                $temp_file = $this->photo;
                $this->photo = array_pop(explode('/',$this->photo));
                try {
                    rename($temp_file, "userUploads/".$company_id."/userImages/".$this->photo);
                }catch(Exception $e) {}
            }
        }
        else {
            
            $this->newPassword = Yii::$app->security->generateRandomString(6);
            
            $this->setPassword($this->newPassword);
            
            $this->company = Company::findOne($this->company_id);
            
            SendMails::send("newUserConfirmation", $this->email, "New User confirmation", $this);
            
        }
        return parent::beforeSave($insert);
    }
    
    public static function getRoleIds() {
        $roleIds = self::getArray(Roles::find()->select("id")->andWhere(["status" => 1])->all(), "id");
        
        return $roleIds;
    }
    
    public static function getArray($obj, $field) {
        $result = [];
        if(count($obj)) {
            foreach($obj as $key => $val)
                $result[] = $val[$field];
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        $userToken = \backend\models\UserTokens::find()
                        ->andWhere("expire_on >= ".time())
                        ->andWhere(['token' => $token, 'expiry_status' => 0])
                        ->one();
        
        if($userToken) {
            $identity = static::findOne(['id' => $userToken->user_id, 'status' => self::STATUS_ACTIVE]);
           
            if($identity) {
                $company = \backend\models\Company::find()
                        ->andWhere(['id' => $identity->company_id, 'company_status' => 1])
                        ->andWhere('expiry_date > '.time())
                        ->one();
                            
                if($company) {
                    return $identity;
                }
                else {
                    Yii::$app->user->logout();
                }
            }
        }
    }
    
    // default scope to check company_id
    public static function find()
    {
        if(isset(\yii::$app->user->identity))
        {
            return parent::find()->where(['user.company_id' => \yii::$app->user->identity->company_id])->andWhere(['<>', 'user.status', self::STATUS_DELETED]);
        }
        else
            return parent::find();
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }
    
    public function attributes() {
        
        $auth = $this->getAuthKey();
        
        return [
            'id',
            'first_name',
            'last_name',
            'email',
            'role',
            'photo',
            'username',
            'contact_number',
            'designation',
            'company_id',
            'password',
            'rec_notification',
            'allow_be',
            'status',
            'created_date',
            'modified_date',
            'auth_key' => $auth,
            'LastTokens'
        ];
    }
    
    public function fields() {
        return [
            'id',
            'first_name',
            'last_name',
            'email',
			'role',
            'role_name' => function() {
                $role = $this->roles;
                if($role)
                    return $role->role_name;
            },            'email',
            'username',
            'contact_number',
            'designation',
            'company_id',
            'rec_notification',
            'allow_be',
            'status',
            'photo',        ];
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return boolean
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        $parts = explode('_', $token);
        $timestamp = (int) end($parts);
        return $timestamp + $expire >= time();
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        $this->generateAuthKey();
        return $this->auth_key;
    }
    
    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return boolean if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password);
    }
    
    public function getLastTokens(){
        if(\yii::$app->user->identity)
            return UserTokens::find()->where(['user_id' => \yii::$app->user->identity->id])->orderBy(['id' => SORT_DESC])->limit(2)->offset(1)->one();
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        if(!\Yii::$app->session->get('user.auth_key')) {
            $this->auth_key = Yii::$app->security->generateRandomString();
            \Yii::$app->session->set('user.auth_key',$this->auth_key);
        }
        else
            $this->auth_key = \Yii::$app->session->get('user.auth_key');
        
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }
    
    public function getUserGroupIds()
    {
        return $this->hasMany(RelUserLevelsUsers::className(), ['id' => 'user_group_id']);
    }

    public function getRoles()
    {
        return $this->hasOne(Roles::className(), ['id' => 'role']);
    }
    
    public function actDelete() {
        $this->status = 2;
        $return = $this->save(FALSE);
        return $return;
    }
}
