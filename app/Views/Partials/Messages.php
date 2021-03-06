<?php foreach(array('success', 'warning', 'danger') as $type) { ?>
	<?php if (Session::has($type)) { ?>

<div class="row">
	<div class="alert alert-<?= $type; ?> alert-dismissable">
		<button type="button" class="close" data-dismiss="alert" aria-hidden="true"><span aria-hidden="true">&times;</span></button>
		<?= Session::get($type); ?>
	</div>
</div>

	<?php } ?>
<?php } ?>

<?php if ($errors->any()) { ?>

<div class="row">
	<div class="alert alert-danger alert-dismissable">
		<button type="button" class="close" data-dismiss="alert" aria-hidden="true"><span aria-hidden="true">&times;</span></button>
		<ul>
			<?php foreach ($errors->all('<li>:message</li>') as $error) { ?>
			<?= $error; ?>
			<?php } ?>
		</ul>
	</div>
</div>

<?php } ?>
