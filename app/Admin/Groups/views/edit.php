<?php
/** @var object $group */ /** @var array $definitions */ /** @var array $overrides */ /** @var string $message */ /** @var string $csrf */
?>
<div class="row justify-content-center"><div class="col-xl-7 col-lg-8"><div class="card border shadow bg-body-tertiary overflow-hidden">
<div class="card-header d-flex justify-content-between align-items-center px-4 py-3"><h1 class="h4 mb-0">Edit Group</h1><a class="btn btn-sm btn-outline-secondary" href="/admin/groups">Back</a></div>
<form method="post"><div class="card-body p-4"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><?= $message ?>
<div class="mb-3"><label class="form-label" for="group-name">Group Name</label><input class="form-control" id="group-name" name="name" value="<?=e($group->name)?>" required></div>
<div class="mb-4"><label class="form-label" for="group-description">Description</label><input class="form-control" id="group-description" name="description" value="<?=e($group->description??'')?>"></div>
<h2 class="h6 mb-3">Permissions</h2>
<div class="card border shadow-none overflow-hidden"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Permission</th><th style="width:180px">Setting</th></tr></thead><tbody>
<?php foreach($definitions as $permission=>$definition):$value=array_key_exists($permission,$overrides)?($overrides[$permission]?'allow':'deny'):'inherit';?><tr><td><strong><?=e($definition['name']??$permission)?></strong><?php if(!empty($definition['description'])):?><div class="small text-body-secondary"><?=e($definition['description'])?></div><?php endif;?></td><td><select class="form-select form-select-sm" name="permissions[<?=e($permission)?>]"><option value="inherit" <?=$value==='inherit'?'selected':''?>>Inherit</option><option value="allow" <?=$value==='allow'?'selected':''?>>Allow</option><option value="deny" <?=$value==='deny'?'selected':''?>>Deny</option></select></td></tr><?php endforeach;?>
</tbody></table></div></div></div>
<div class="card-footer d-flex justify-content-end gap-2 px-4 py-3"><a class="btn btn-outline-secondary" href="/admin/groups">Cancel</a><button class="button" type="submit">Save Changes</button></div></form>
</div></div></div>
