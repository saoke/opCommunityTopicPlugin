<?php
class opCommunityTopicPluginAPIActions extends opJsonApiActions
{
  protected function getValidTarget($request)
  {
    if (!isset($request['target']))
    {
      throw new Exception('target is not specified');
    }

    if (!isset($request['target_id']) || '' == $request['target_id'])
    {
      throw new Exception($request['target'].'_id is not specified');
    }

    switch ($request['target'])
    {
      case 'community':
      case 'member':
      case 'event':
      case 'topic':
        return $request['target'];
        break;
      default:
        throw new Exception('invalid target');
    }
  }

  protected function getOptions($request)
  {
    return array(
      'limit' => isset($request['count']) ? $request['count'] : sfConfig::get('op_json_api_limit', 15),
      'max_id' => $request['max_id'] ? $request['max_id'] : null,
      'since_id' => $request['since_id'] ? $request['since_id'] : null,
      'page' => $request->getParameter('page', 1),
    );
  }

  protected function getEventByEventId($id)
  {
    if (!$event = Doctrine::getTable('CommunityEvent')->findOneById($id))
    {
      $this->forward400('the specified event does not exist.');
    }

    return $event;
  }

  protected function getTopicByTopicId($id)
  {
    if (!$topic = Doctrine::getTable('CommunityTopic')->findOneById($id))
    {
      $this->forward400('the specified topic does not exist.');
    }

    return $topic;
  }

  protected function getViewableEvent($id, $memberId)
  {
    $event = $this->getEventByEventId($id);
    if ($event)
    {
      $event->actAs('opIsCreatableCommunityTopicBehavior');
      if(!$event->isViewableCommunityTopic($event->getCommunity(), $memberId))
      {
        $this->forward400('you are not allowed to view event on this community');
      }

      return $event;
    }

    return false;
  }

  protected function getViewableTopic($id, $memberId)
  {
    $topic = $this->getTopicByTopicId($id);
    if ($topic)
    {
      $topic->actAs('opIsCreatableCommunityTopicBehavior');
      if (!$topic->isViewableCommunityTopic($topic->getCommunity(), $memberId))
      {
        $this->forward400('you are not allowed to view this topic and comments on this community');
      }

      return $topic;
    }

    return false;
  }

  protected function isValidNameAndBody($name, $body)
  {
    if (!$name || !$body)
    {
      $this->forward400('name and body parameter required');
    }

    try
    {
      $validator = new opValidatorString(array('trim' => true));
      $cleanName = $validator->clean($name);
      $cleanBody = $validator->clean($body);
    }
    catch (sfValidatorError $e)
    {
      $this->forward400Unless(isset($cleanName), 'name parameter is not specified.');
      $this->forward400Unless(isset($cleanBody), 'body parameter is not specified.');
    }
  }

  protected function searchEventsByCommunityId($targetId, $options)
  {
    $table = Doctrine::getTable('CommunityEvent');
    $query = $table->createQuery()
      ->where('community_id = ?', $targetId)
      ->orderBy('event_updated_at DESC');

    if($options['max_id'])
    {
      $query->addWhere('id <= ?', $options['max_id']);
    }

    if($options['since_id'])
    {
      $query->addWhere('id > ?', $options['since_id']);
    }

    $pager = $table->getResultListPager($query, $options['page'], $options['limit']);

    return $pager->getResults();
  }

  protected function searchTopicsByCommunityId($targetId, $options)
  {
    $table = Doctrine::getTable('CommunityTopic');
    $query = $table->createQuery('t')
      ->where('community_id = ?', $targetId)
      ->orderBy('topic_updated_at DESC');

    if($options['max_id'])
    {
      $query->addWhere('id <= ?', $options['max_id']);
    }

    if($options['since_id'])
    {
      $query->addWhere('id > ?', $options['since_id']);
    }

    $pager = $table->getResultListPager($query, $options['page'], $options['limit']);

    return $pager->getResults();
  }

  protected function getEvents($target, $targetId, $options)
  {
    $events = array();

    if ('community' == $target)
    {
      $events = $this->searchEventsByCommunityId($targetId, $options);
    }
    elseif ('member' == $target)
    {
      if (!$member = Doctrine::getTable('Member')->findOneById($targetId))
      {
        throw new Exception('target_id is invalid');
      }

      $events = Doctrine::getTable('CommunityEvent')->retrivesByMemberId($member->getId(), $options['limit']);
    }

    return $events;
  }

  protected function getTopics($target, $targetId, $options)
  {
    $topic = array();

    if ('community' == $target)
    {
      $topics = $this->searchTopicsByCommunityId($targetId, $options);
    }
    elseif ('member' == $target)
    {
      if (!$member = Doctrine::getTable('Member')->findOneById($targetId))
      {
        $this->forward400('target_id is invalid');
      }
      $topics = Doctrine::getTable('CommunityTopic')->retrivesByMemberId($member->getId(), $options['limit']);
    }

    return $topics;
  }

  protected function getPager($tableName, $query, $page = 1, $size = 15)
  {
    $pager = new sfDoctrinePager($tableName, $size);
    $pager->setQuery($query);
    $pager->setPage($page);
    $pager->init();

    return $pager;
  }
}
