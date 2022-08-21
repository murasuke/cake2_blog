<h1>Blog posts</h1>
<?php echo $this->Html->link(
  'Add User',
  'add'
); ?>
<table>
  <tr>
    <th>Id</th>
    <th>UserName</th>
    <th>Role</th>
    <th>Created</th>
    <th>Action</th>
  </tr>

  <?php foreach($users as $user): ?>
  <tr>
    <td><?php echo $user['User']['id']; ?></td>
    <td><?php echo $this->Html->link($user['User']['username'], array('controller' => 'users','action' => 'view', $user['User']['id']));?></td>
    <td><?php echo $user['User']['role']; ?></td>
    <td><?php echo $user['User']['created']; ?></td>
    <td>
      <?php echo $this->Form->postLink('Delete', array('action' => 'delete', $user['User']['id']), array('confirm' => 'Are you sure?')); ?>
      <?php echo $this->Html->link('Edit', array('action' => 'edit', $user['User']['id'])); ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php unset($post); ?>
</table>
