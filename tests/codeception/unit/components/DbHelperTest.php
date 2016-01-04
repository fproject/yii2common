<?php

namespace tests\codeception\unit\components;

use Codeception\Util\Debug;
use tests\codeception\unit\models\base\Department;
use tests\codeception\unit\models\base\UserDepartmentAssignment;
use tests\codeception\unit\models\User;
use Yii;
use yii\codeception\TestCase;
use fproject\components\DbHelper;
use yii\helpers\Json;

class DbHelperTest extends TestCase
{
	use \Codeception\Specify;

    public function testCreateMultipleUpdateCommand001()
    {
        $cmd = DbHelper::createMultipleUpdateCommand('user',[],'id');
        $this->assertInstanceOf('yii\db\Command', $cmd);
    }

    public function testBatchSave001()
    {
        /** @var User[] $models */
        $models = [];
        for($i=0;$i<10;$i++)
        {
            $m = $models[] = new User();
            $m->username = "New User $i-".rand(1000,9999);
            $m->password = $m->username;
        }
        $return = DbHelper::batchSave($models);
        $this->assertObjectNotHasAttribute('updateCount', $return);
        $this->assertObjectHasAttribute('insertCount', $return);
        $this->assertEquals(10, $return->insertCount);
        $this->assertObjectHasAttribute('lastId', $return);

        Debug::debug('Inserted 10 users with LAST_ID='.$return->lastId);

        /** @var User $lastUser */
        $lastUser = User::findOne($return->lastId);
        /** @var User $firstUser */
        $firstUser = User::findOne($return->lastId - $return->insertCount + 1);
        $this->assertNotEmpty($lastUser);
        $this->assertNotEmpty($firstUser);
        $this->assertEquals($models[0]->username, $firstUser->username);
        $this->assertEquals($models[9]->username, $lastUser->username);
    }

    public function testBatchSave002()
    {

        /** @var User[] $inputModels */
        $inputModels = [];
        for($i=0;$i<10;$i++)
        {
            $m = $inputModels[] = new User();
            $m->username = "New User $i-".rand(1000,9999);
            $m->password = $m->username;
        }

        /** @var array $savedReturn */
        $savedReturn = [];
        $return = DbHelper::batchSave($inputModels, [], DbHelper::SAVE_MODE_AUTO, $savedReturn);

        $this->assertObjectNotHasAttribute('updateCount', $return);
        $this->assertObjectHasAttribute('insertCount', $return);
        $this->assertEquals(10, $return->insertCount);
        $this->assertObjectHasAttribute('lastId', $return);



        /** @var User $lastUser */
        $lastUser = User::findOne($return->lastId);
        /** @var User $firstUser */
        $firstUser = User::findOne($return->lastId - $return->insertCount + 1);
        $this->assertNotEmpty($lastUser);
        $this->assertNotEmpty($firstUser);
        $this->assertEquals($inputModels[0]->username, $firstUser->username);
        $this->assertEquals($inputModels[9]->username, $lastUser->username);

        $this->assertArrayHasKey('inserted', $savedReturn);
        $this->assertArrayNotHasKey('updated', $savedReturn);

        for($i=0; $i<10; $i++)
        {
            /** @var User $m */
            $m = $savedReturn['inserted'][$i];
            $this->assertEquals($return->lastId - 9 + $i, $m->id);
        }

        $inputModels = $savedReturn['inserted'];
        for($i=0;$i<10;$i++)
        {
            $m = $inputModels[] = new User();
            $m->username = "New User $i-".rand(1000,9999);
            $m->password = $m->username;
        }

        $updatedLastId = $return->lastId;

        $return = DbHelper::batchSave($inputModels, [], DbHelper::SAVE_MODE_AUTO, $savedReturn);
        $this->assertObjectHasAttribute('updateCount', $return);
        $this->assertEquals(10, $return->updateCount);
        $this->assertObjectHasAttribute('insertCount', $return);
        $this->assertEquals(10, $return->insertCount);
        $this->assertObjectHasAttribute('lastId', $return);

        $this->assertArrayHasKey('inserted', $savedReturn);
        $this->assertArrayHasKey('updated', $savedReturn);

        for($i=0; $i<10; $i++)
        {
            /** @var User $m */
            $m = $savedReturn['inserted'][$i];
            $this->assertEquals($return->lastId - 9 + $i, $m->id);
            $m = $savedReturn['updated'][$i];
            $this->assertEquals($updatedLastId - 9 + $i, $m->id);
        }
    }

    public function testBatchSaveForNoIncrementIdModel()
    {
        /** @var User[] $inputModels */
        $inputModels = [];
        for($i=0;$i<10;$i++)
        {
            $m = $inputModels[] = new User();
            $m->username = "New User $i-".rand(10000,99999);
            $m->password = $m->username;
        }

        /** @var User[] $savedReturn */
        $savedReturn = [];
        $return = DbHelper::batchSave($inputModels, [], DbHelper::SAVE_MODE_AUTO, $savedReturn);

        Debug::debug('Inserted 10 users with LAST_ID='.$return->lastId);

        $this->assertArrayHasKey('inserted', $savedReturn);
        $this->assertArrayNotHasKey('updated', $savedReturn);
        /** @var User[] $savedUsers */
        $savedUsers = $savedReturn['inserted'];

        //Debug::debug(Json::encode($savedUsers));

        $department = new Department();
        $department->name = "Department testBatchSaveForNoIncrementIdField";
        $department->save(false);

        Debug::debug('Inserted a department with ID = '.$department->id);

        /** @var UserDepartmentAssignment[] $inputModels */
        $inputModels = [];
        foreach($savedUsers as $savedUser)
        {
            $m = $inputModels[] = new UserDepartmentAssignment();
            $m->userId = $savedUser->id;
            $m->departmentId = $department->id;
        }

        Debug::debug('Before saving 10 UserDepartmentAssignment records. '.Json::encode($inputModels));

        $return = DbHelper::batchSave($inputModels, [], DbHelper::SAVE_MODE_AUTO);

        Debug::debug('Batch saved 10 UserDepartmentAssignment records. '.Json::encode($return));

        $this->assertObjectNotHasAttribute('updateCount', $return);
        $this->assertObjectHasAttribute('insertCount', $return);
        $this->assertEquals(10, $return->insertCount);
        $this->assertObjectHasAttribute('lastId', $return);
    }

    public function testBatchDeleteForNoIncrementIdModel()
    {
        /** @var User[] $inputModels */
        $inputModels = [];
        for($i=0;$i<10;$i++)
        {
            $m = $inputModels[] = new User();
            $m->username = "New User $i-".rand(10000,99999);
            $m->password = $m->username;
        }

        /** @var User[] $savedReturn */
        $savedReturn = [];
        DbHelper::batchSave($inputModels, [], DbHelper::SAVE_MODE_AUTO, $savedReturn);

        /** @var User[] $savedUsers */
        $savedUsers = $savedReturn['inserted'];

        $department = new Department();
        $department->name = "Department testBatchSaveForNoIncrementIdField";
        $department->save(false);

        /** @var UserDepartmentAssignment[] $inputModels */
        $inputModels = [];
        $ids = [];
        $sql = '';
        foreach($savedUsers as $savedUser)
        {
            $m = $inputModels[] = new UserDepartmentAssignment();
            $m->userId = $savedUser->id;
            $m->departmentId = $department->id;
            $ids[] = ['userId'=>$savedUser->id,'departmentId'=>$department->id];
            if($sql != '')
                $sql = $sql.' OR ';
            $sql = $sql."(`userId`=$savedUser->id AND `departmentId`=$department->id)";
        }

        DbHelper::batchSave($inputModels, [], DbHelper::SAVE_MODE_AUTO);

        $return = DbHelper::batchDelete(UserDepartmentAssignment::tableName(), $ids);
        $this->assertEquals(10, $return);

        $sql = 'SELECT * FROM '.UserDepartmentAssignment::tableName().' WHERE '.$sql;

        $return = UserDepartmentAssignment::findBySql($sql)->count();
        $this->assertEquals(0, $return);
    }
}
