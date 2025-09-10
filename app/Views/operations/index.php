<?php include __DIR__.'/../layout/header.php'; ?>
<option value="<?= $s ?>" <?= ($filters['status']??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-2"><input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>" class="form-control"/></div>
<div class="col-md-2"><input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? '') ?>" class="form-control"/></div>
<div class="col-md-2 d-grid"><button class="btn btn-primary">Filtrar</button></div>
</form>


<?php
$data = $result['data'] ?? [];
$page = $result['page'] ?? 1; $per=$result['per_page']??10; $total=$result['total']??0;
$pages = max(1, (int)ceil($total / $per));
$invert = $dir==='asc'?'desc':'asc';
$qs = fn($o) => http_build_query(array_merge($_GET, ['order'=>$o,'dir'=>$invert,'page'=>1]));
?>


<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
<th><a href="?<?= $qs('id') ?>">#</a></th>
<th><a href="?<?= $qs('code') ?>">Código</a></th>
<th><a href="?<?= $qs('title') ?>">Título</a></th>
<th><a href="?<?= $qs('status') ?>">Status</a></th>
<th><a href="?<?= $qs('due_date') ?>">Vencimento</a></th>
<th class="text-end"><a href="?<?= $qs('amount') ?>">Valor</a></th>
<th></th>
</tr>
</thead>
<tbody>
<?php if (!$data): ?>
<tr><td colspan="7" class="text-center text-muted">Nenhuma operação encontrada.</td></tr>
<?php else: foreach ($data as $row): ?>
<tr>
<td><?= (int)$row['id'] ?></td>
<td><?= htmlspecialchars($row['code']) ?></td>
<td><?= htmlspecialchars($row['title']) ?></td>
<td><span class="badge text-bg-<?= $row['status']==='active'?'success':($row['status']==='draft'?'secondary':($row['status']==='settled'?'info':'danger')) ?>"><?= ucfirst($row['status']) ?></span></td>
<td><?= htmlspecialchars($row['due_date'] ?? '-') ?></td>
<td class="text-end">R$ <?= number_format((float)$row['amount'], 2, ',', '.') ?></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/operations/<?= (int)$row['id'] ?>">Detalhes</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>


<nav aria-label="Paginação">
<ul class="pagination justify-content-end">
<?php for ($p=1; $p <= $pages; $p++): ?>
<?php $q = http_build_query(array_merge($_GET, ['page'=>$p])); ?>
<li class="page-item <?= $p===$page?'active':'' "><a class="page-link" href="?<?= $q ?>"><?= $p ?></a></li>
<?php endfor; ?>
</ul>
</nav>


<?php include __DIR__.'/../layout/footer.php'; ?>