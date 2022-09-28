<div style="margin-left:10px;">
  <p>child element</p>
  <h2><?= h($post['Post']['title']); ?></h2>

  <?php echo $this->element('grandchild'); ?>
</div>


