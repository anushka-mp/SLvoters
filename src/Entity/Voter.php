<?php

/**
 * @file
 * Contains Drupal\SLvoters\Entity\Voter.
 */

namespace Drupal\SLvoters\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\SLvoters\voterInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the SLvoter voter entity.
 *
 * @ContentEntityType(
 *   id = "SLvoter_voter",
 *   label = @Translation("SLvoters voter"),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\SLvoters\Form\voterForm",
 *       "account" = "Drupal\SLvoters\Form\votersAccountForm",
 *       "block" = "Drupal\SLvoters\Form\votersBlockForm",
 *       "page" = "Drupal\SLvoters\Form\votersPageForm",
 *       "delete" = "Drupal\SLvoters\Form\voterDeleteForm",
 *     },
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\SLvoters\voterViewsData"
 *   },
 *   base_table = "SLvoters_voter",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "mail"
 *   },
 *   field_ui_base_route = "SLvoters.settings_voter",
 *   admin_permission = "administer SLvoters voters",
 *   links = {
 *     "edit-form" = "/admin/people/SLvoters/edit/{SLvoters_voter}",
 *     "delete-form" = "/admin/people/SLvoters/delete/{SLvoters_voter}",
 *   }
 * )
 */
class Voter extends ContentEntityBase implements VoterInterface {

  /**
   * Whether currently copying field values to corresponding User.
   *
   * @var bool
   */
  protected static $syncing;

  /**
   * {@inheritdoc}
   */
  public function getMessage() {
    return $this->get('message')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage($message) {
    $this->set('message', $message);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->set('status', $status);
  }

  /**
   * {@inheritdoc}
   */
  public function getMail() {
    return $this->get('mail')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMail($mail) {
    $this->set('mail', $mail);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserId() {
    $value = $this->get('uid')->getValue();
    if (isset($value[0]['target_id'])) {
      return $value[0]['target_id'];
    }
    return '0';
  }

  /**
   * {@inheritdoc}
   */
  public function getUser() {
    $mail = $this->getMail();

    if (empty($mail)) {
      return NULL;
    }
    if ($user = User::load($this->getUserId())) {
      return $user;
    }
    else {
      return user_load_by_mail($this->getMail()) ?: NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setUserId($uid) {
    $this->set('uid', $uid);
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode() {
    return $this->get('langcode')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLangcode($langcode) {
    $this->set('langcode', $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getChanges() {
    return unserialize($this->get('changes')->value);
  }

  /**
   * {@inheritdoc}
   */
  public function setChanges($changes) {
    $this->set('changes', serialize($changes));
  }

  /**
   * {@inheritdoc}
   */
  public function isSyncing() {
    return static::$syncing;
  }

  /**
   * {@inheritdoc}
   */
  public function isVoter($voter) {
      if ($voter->status == SLvoters_STATUS_VOTER) {
        return TRUE;
    }
    return FALSE;
  }
  
  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Copy values for shared fields to existing user.
    if (\Drupal::config('SLvoters.settings')->get('voter.sync_fields') && $user = $this->getUser()) {
      static::$syncing = TRUE;
      foreach ($this->getUserSharedFields($user) as $field_name) {
        $user->set($field_name, $this->get($field_name)->getValue());
      }
      $user->save();
      static::$syncing = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {
    parent::postCreate($storage);

    // Set the uid field if there is a user with the same email.
    $user_ids = \Drupal::entityQuery('user')
      ->condition('mail', $this->getMail())
      ->execute();
    if (!empty($user_ids)) {
      $this->setUserId(array_pop($user_ids));
    }

    // Copy values for shared fields from existing user.
    if (\Drupal::config('SLvoters.settings')->get('voter.sync_fields') && $user = $this->getUser()) {
      foreach ($this->getUserSharedFields($user) as $field_name) {
        $this->set($field_name, $user->get($field_name)->getValue());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUserSharedFields(UserInterface $user) {
    $field_names = array();
    // Find any fields sharing name and type.
    foreach ($this->getFieldDefinitions() as $field_definition) {
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
      $field_name = $field_definition->getName();
      $user_field = $user->getFieldDefinition($field_name);
      if ($field_definition->getTargetBundle() && isset($user_field) && $user_field->getType() == $field_definition->getType()) {
        $field_names[] = $field_name;
      }
    }
    return $field_names;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('voter ID'))
      ->setDescription(t('Primary key: Unique voter ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The voter UUID.'))
      ->setReadOnly(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('Boolean indicating the status of the voter.'))
      ->setDefaultValue(TRUE);

    $fields['mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDescription(t("The voter's email address."))
      ->setSetting('default_value', '')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', array(
        'type' => 'email',
        'settings' => array(),
        'weight' => 5,
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The corresponding user.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('form', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setDescription(t("The voter's preferred language."));

    $fields['changes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Changes'))
      ->setDescription(t('Contains the requested subscription changes.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the voter was created.'));

    return $fields;
  }

}
