<?php

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Versioning\ChangeSet;
use SilverStripe\ORM\Versioning\ChangeSetItem;
use SilverStripe\ORM\Versioning\Versioned;
use SilverStripe\Security\Permission;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\Session;


/**
 * Provides a set of targettable permissions for tested models
 *
 * @mixin Versioned
 * @mixin DataObject
 */
trait ChangeSetTest_Permissions {
	public function canEdit($member = null) {
		return $this->can(__FUNCTION__, $member);
	}

	public function canDelete($member = null) {
		return $this->can(__FUNCTION__, $member);
	}

	public function canCreate($member = null, $context = array()) {
		return $this->can(__FUNCTION__, $member, $context);
	}

	public function canPublish($member = null, $context = array()) {
		return $this->can(__FUNCTION__, $member, $context);
	}

	public function canUnpublish($member = null, $context = array()) {
		return $this->can(__FUNCTION__, $member, $context);
	}

	public function can($perm, $member = null, $context = array()) {
		$perms = [
			"PERM_{$perm}",
			'CAN_ALL',
		];
		return Permission::checkMember($member, $perms);
	}
}

/**
 * @mixin Versioned
 */
class ChangeSetTest_Base extends DataObject implements TestOnly {
	use ChangeSetTest_Permissions;

	private static $db = [
		'Foo' => 'Int',
	];

	private static $has_many = [
		'Mids' => ChangeSetTest_Mid::class,
	];

	private static $owns = [
		'Mids',
	];

	private static $extensions = [
		"SilverStripe\\ORM\\Versioning\\Versioned",
	];
}

/**
 * @mixin Versioned
 */
class ChangeSetTest_Mid extends DataObject implements TestOnly {
	use ChangeSetTest_Permissions;

	private static $db = [
		'Bar' => 'Int',
	];

	private static $has_one = [
		'Base' => ChangeSetTest_Base::class,
		'End' => ChangeSetTest_End::class,
	];

	private static $owns = [
		'End',
	];

	private static $extensions = [
		"SilverStripe\\ORM\\Versioning\\Versioned",
	];
}

/**
 * @mixin Versioned
 */
class ChangeSetTest_End extends DataObject implements TestOnly {
	use ChangeSetTest_Permissions;

	private static $db = [
		'Baz' => 'Int',
	];

	private static $extensions = [
		"SilverStripe\\ORM\\Versioning\\Versioned",
	];
}

/**
 * @mixin Versioned
 */
class ChangeSetTest_EndChild extends ChangeSetTest_End implements TestOnly {

	private static $db = [
		'Qux' => 'Int',
	];
}

/**
 * Test {@see ChangeSet} and {@see ChangeSetItem} models
 */
class ChangeSetTest extends SapphireTest {

	protected static $fixture_file = 'ChangeSetTest.yml';

	protected $extraDataObjects = [
		ChangeSetTest_Base::class,
		ChangeSetTest_Mid::class,
		ChangeSetTest_End::class,
		ChangeSetTest_EndChild::class,
	];

	/**
	 * Automatically publish all objects
	 */
	protected function publishAllFixtures() {
		$this->logInWithPermission('ADMIN');
		foreach($this->fixtureFactory->getFixtures() as $class => $fixtures) {
			foreach ($fixtures as $handle => $id) {
				/** @var Versioned|DataObject $object */
				$object = $this->objFromFixture($class, $handle);
				$object->publishSingle();
			}
		}
	}

	/**
	 * Check that the changeset includes the given items
	 *
	 * @param ChangeSet $cs
	 * @param array $match Array of object fixture keys with change type values
	 */
	protected function assertChangeSetLooksLike($cs, $match) {
		$items = $cs->Changes()->toArray();

		foreach($match as $key => $mode) {
			list($class, $identifier) = explode('.', $key);
			$object = $this->objFromFixture($class, $identifier);

			foreach($items as $i => $item) {
				if ( $item->ObjectClass == $object->baseClass()
					&& $item->ObjectID == $object->ID
					&& $item->Added == $mode
				) {
					unset($items[$i]);
					continue 2;
				}
			}

			throw new PHPUnit_Framework_ExpectationFailedException(
				'Change set didn\'t include expected item',
				new \SebastianBergmann\Comparator\ComparisonFailure(array('Class' => $class, 'ID' => $object->ID, 'Added' => $mode), null, "$key => $mode", '')
			);
		}

		if (count($items)) {
			$extra = [];
			foreach ($items as $item) $extra[] = ['Class' => $item->ObjectClass, 'ID' => $item->ObjectID, 'Added' => $item->Added, 'ChangeType' => $item->getChangeType()];
			throw new PHPUnit_Framework_ExpectationFailedException(
				'Change set included items that weren\'t expected',
				new \SebastianBergmann\Comparator\ComparisonFailure(array(), $extra, '', print_r($extra, true))
			);
		}
	}

	public function testAddObject() {
		$cs = new ChangeSet();
		$cs->write();

		$cs->addObject($this->objFromFixture(ChangeSetTest_End::class, 'end1'));
		$cs->addObject($this->objFromFixture(ChangeSetTest_EndChild::class, 'endchild1'));

		$this->assertChangeSetLooksLike($cs, [
			'ChangeSetTest_End.end1' => ChangeSetItem::EXPLICITLY,
			'ChangeSetTest_EndChild.endchild1' => ChangeSetItem::EXPLICITLY
		]);
	}

	public function testRepeatedSyncIsNOP() {
		$this->publishAllFixtures();

		$cs = new ChangeSet();
		$cs->write();

		$base = $this->objFromFixture(ChangeSetTest_Base::class, 'base');
		$cs->addObject($base);

		$cs->sync();
		$this->assertChangeSetLooksLike($cs, [
			'ChangeSetTest_Base.base' => ChangeSetItem::EXPLICITLY
		]);

		$cs->sync();
		$this->assertChangeSetLooksLike($cs, [
			'ChangeSetTest_Base.base' => ChangeSetItem::EXPLICITLY
		]);
	}

	public function testSync() {
		$this->publishAllFixtures();

		$cs = new ChangeSet();
		$cs->write();

		$base = $this->objFromFixture(ChangeSetTest_Base::class, 'base');

		$cs->addObject($base);
		$cs->sync();

		$this->assertChangeSetLooksLike($cs, [
			'ChangeSetTest_Base.base' => ChangeSetItem::EXPLICITLY
		]);

		$end = $this->objFromFixture(ChangeSetTest_End::class, 'end1');
		$end->Baz = 3;
		$end->write();

		$cs->sync();

		$this->assertChangeSetLooksLike($cs, [
			'ChangeSetTest_Base.base' => ChangeSetItem::EXPLICITLY,
			'ChangeSetTest_End.end1' => ChangeSetItem::IMPLICITLY
		]);

		$baseItem = ChangeSetItem::get_for_object($base)->first();
		$endItem = ChangeSetItem::get_for_object($end)->first();

		$this->assertEquals(
			[$baseItem->ID],
			$endItem->ReferencedBy()->column("ID")
		);

		$this->assertDOSEquals([
			[
				'Added' => ChangeSetItem::EXPLICITLY,
				'ObjectClass' => ChangeSetTest_Base::class,
				'ObjectID' => $base->ID,
				'ChangeSetID' => $cs->ID
			]
		], $endItem->ReferencedBy());
	}

	/**
	 * Test that sync includes implicit items
	 */
	public function testIsSynced() {
		$this->publishAllFixtures();

		$cs = new ChangeSet();
		$cs->write();

		$base = $this->objFromFixture(ChangeSetTest_Base::class, 'base');
		$cs->addObject($base);

		$cs->sync();
		$this->assertChangeSetLooksLike($cs, [
			'ChangeSetTest_Base.base' => ChangeSetItem::EXPLICITLY
		]);
		$this->assertTrue($cs->isSynced());

		$end = $this->objFromFixture(ChangeSetTest_End::class, 'end1');
		$end->Baz = 3;
		$end->write();
		$this->assertFalse($cs->isSynced());

		$cs->sync();

		$this->assertChangeSetLooksLike($cs, [
			'ChangeSetTest_Base.base' => ChangeSetItem::EXPLICITLY,
			'ChangeSetTest_End.end1' => ChangeSetItem::IMPLICITLY
		]);
		$this->assertTrue($cs->isSynced());
	}

	public function testCanPublish() {
		// Create changeset containing all items (unpublished)
		$this->logInWithPermission('ADMIN');
		$changeSet = new ChangeSet();
		$changeSet->write();
		$base = $this->objFromFixture(ChangeSetTest_Base::class, 'base');
		$changeSet->addObject($base);
		$changeSet->sync();
		$this->assertEquals(5, $changeSet->Changes()->count());

		// Test un-authenticated user cannot publish
		Session::clear("loggedInAs");
		$this->assertFalse($changeSet->canPublish());

		// campaign admin only permission doesn't grant publishing rights
		$this->logInWithPermission('CMS_ACCESS_CampaignAdmin');
		$this->assertFalse($changeSet->canPublish());

		// With model publish permissions only publish is allowed
		$this->logInWithPermission('PERM_canPublish');
		$this->assertTrue($changeSet->canPublish());

		// Test user with the necessary minimum permissions can login
		$this->logInWithPermission([
			'CMS_ACCESS_CampaignAdmin',
			'PERM_canPublish'
		]);
		$this->assertTrue($changeSet->canPublish());
	}

	public function testCanRevert() {
		$this->markTestSkipped("Requires ChangeSet::revert to be implemented first");
	}

	public function testCanEdit() {
		// Create changeset containing all items (unpublished)
		$this->logInWithPermission('ADMIN');
		$changeSet = new ChangeSet();
		$changeSet->write();
		$base = $this->objFromFixture(ChangeSetTest_Base::class, 'base');
		$changeSet->addObject($base);
		$changeSet->sync();
		$this->assertEquals(5, $changeSet->Changes()->count());

		// Check canEdit
		Session::clear("loggedInAs");
		$this->assertFalse($changeSet->canEdit());
		$this->logInWithPermission('SomeWrongPermission');
		$this->assertFalse($changeSet->canEdit());
		$this->logInWithPermission('CMS_ACCESS_CampaignAdmin');
		$this->assertTrue($changeSet->canEdit());
	}

	public function testCanCreate() {
		// Check canCreate
		Session::clear("loggedInAs");
		$this->assertFalse(ChangeSet::singleton()->canCreate());
		$this->logInWithPermission('SomeWrongPermission');
		$this->assertFalse(ChangeSet::singleton()->canCreate());
		$this->logInWithPermission('CMS_ACCESS_CampaignAdmin');
		$this->assertTrue(ChangeSet::singleton()->canCreate());
	}

	public function testCanDelete() {
		// Create changeset containing all items (unpublished)
		$this->logInWithPermission('ADMIN');
		$changeSet = new ChangeSet();
		$changeSet->write();
		$base = $this->objFromFixture(ChangeSetTest_Base::class, 'base');
		$changeSet->addObject($base);
		$changeSet->sync();
		$this->assertEquals(5, $changeSet->Changes()->count());

		// Check canDelete
		Session::clear("loggedInAs");
		$this->assertFalse($changeSet->canDelete());
		$this->logInWithPermission('SomeWrongPermission');
		$this->assertFalse($changeSet->canDelete());
		$this->logInWithPermission('CMS_ACCESS_CampaignAdmin');
		$this->assertTrue($changeSet->canDelete());
	}

	public function testCanView() {
		// Create changeset containing all items (unpublished)
		$this->logInWithPermission('ADMIN');
		$changeSet = new ChangeSet();
		$changeSet->write();
		$base = $this->objFromFixture(ChangeSetTest_Base::class, 'base');
		$changeSet->addObject($base);
		$changeSet->sync();
		$this->assertEquals(5, $changeSet->Changes()->count());

		// Check canView
		Session::clear("loggedInAs");
		$this->assertFalse($changeSet->canView());
		$this->logInWithPermission('SomeWrongPermission');
		$this->assertFalse($changeSet->canView());
		$this->logInWithPermission('CMS_ACCESS_CampaignAdmin');
		$this->assertTrue($changeSet->canView());
	}

	public function testPublish() {
		$this->publishAllFixtures();

		$base = $this->objFromFixture(ChangeSetTest_Base::class, 'base');
		$baseID = $base->ID;
		$baseBefore = $base->Version;
		$end1 = $this->objFromFixture(ChangeSetTest_End::class, 'end1');
		$end1ID = $end1->ID;
		$end1Before = $end1->Version;

		// Create a new changest
		$changeset = new ChangeSet();
		$changeset->write();
		$changeset->addObject($base);
		$changeset->addObject($end1);

		// Make a lot of changes
		// - ChangeSetTest_Base.base modified
		// - ChangeSetTest_End.end1 deleted
		// - new ChangeSetTest_Mid added
		$base->Foo = 343;
		$base->write();
		$baseAfter = $base->Version;
		$midNew = new ChangeSetTest_Mid();
		$midNew->Bar = 39;
		$midNew->write();
		$midNewID = $midNew->ID;
		$midNewAfter = $midNew->Version;
		$end1->delete();

		$changeset->addObject($midNew);

		// Publish
		$this->logInWithPermission('ADMIN');
		$this->assertTrue($changeset->canPublish());
		$this->assertTrue($changeset->isSynced());
		$changeset->publish();
		$this->assertEquals(ChangeSet::STATE_PUBLISHED, $changeset->State);

		// Check each item has the correct before/after version applied
		$baseChange = $changeset->Changes()->filter([
			'ObjectClass' => ChangeSetTest_Base::class,
			'ObjectID' => $baseID,
		])->first();
		$this->assertEquals((int)$baseBefore, (int)$baseChange->VersionBefore);
		$this->assertEquals((int)$baseAfter, (int)$baseChange->VersionAfter);
		$this->assertEquals((int)$baseChange->VersionBefore + 1, (int)$baseChange->VersionAfter);
		$this->assertEquals(
			(int)$baseChange->VersionAfter,
			(int)Versioned::get_versionnumber_by_stage(ChangeSetTest_Base::class, Versioned::LIVE, $baseID)
		);

		$end1Change = $changeset->Changes()->filter([
			'ObjectClass' => ChangeSetTest_End::class,
			'ObjectID' => $end1ID,
		])->first();
		$this->assertEquals((int)$end1Before, (int)$end1Change->VersionBefore);
		$this->assertEquals(0, (int)$end1Change->VersionAfter);
		$this->assertEquals(
			0,
			(int)Versioned::get_versionnumber_by_stage(ChangeSetTest_End::class, Versioned::LIVE, $end1ID)
		);

		$midNewChange = $changeset->Changes()->filter([
			'ObjectClass' => ChangeSetTest_Mid::class,
			'ObjectID' => $midNewID,
		])->first();
		$this->assertEquals(0, (int)$midNewChange->VersionBefore);
		$this->assertEquals((int)$midNewAfter, (int)$midNewChange->VersionAfter);
		$this->assertEquals(
			(int)$midNewAfter,
			(int)Versioned::get_versionnumber_by_stage(ChangeSetTest_Mid::class, Versioned::LIVE, $midNewID)
		);

		// Test trying to re-publish is blocked
		$this->setExpectedException(
			'BadMethodCallException',
			"ChangeSet can't be published if it has been already published or reverted."
		);
		$changeset->publish();
	}

	/**
	 * Ensure that related objects are disassociated on live
	 */
	public function testUnlinkDisassociated() {
		$this->publishAllFixtures();
		/** @var ChangeSetTest_Base $base */
		$base = $this->objFromFixture(ChangeSetTest_Base::class, 'base');
		/** @var ChangeSetTest_Mid $mid1 $mid2 */
		$mid1 = $this->objFromFixture(ChangeSetTest_Mid::class, 'mid1');
		$mid2 = $this->objFromFixture(ChangeSetTest_Mid::class, 'mid2');

		// Remove mid1 from stage
		$this->assertEquals($base->ID, $mid1->BaseID);
		$this->assertEquals($base->ID, $mid2->BaseID);
		$mid1->deleteFromStage(Versioned::DRAFT);

		// Publishing recursively should unlinkd this object
		$changeset = new ChangeSet();
		$changeset->write();
		$changeset->addObject($base);

		// Assert changeset only contains root object
		$this->assertChangeSetLooksLike($changeset, [
			'ChangeSetTest_Base.base' => ChangeSetItem::EXPLICITLY
		]);

		$changeset->publish();

		// mid1 on live exists, but has BaseID set to zero
		$mid1Live = Versioned::get_by_stage(ChangeSetTest_Mid::class, Versioned::LIVE)
			->byID($mid1->ID);
		$this->assertNotNull($mid1Live);
		$this->assertEquals($mid1->ID, $mid1Live->ID);
		$this->assertEquals(0, $mid1Live->BaseID);

		// mid2 on live exists and retains BaseID
		$mid2Live = Versioned::get_by_stage(ChangeSetTest_Mid::class, Versioned::LIVE)
			->byID($mid2->ID);
		$this->assertNotNull($mid2Live);
		$this->assertEquals($mid2->ID, $mid2Live->ID);
		$this->assertEquals($base->ID, $mid2Live->BaseID);
	}

}
