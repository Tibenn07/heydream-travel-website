<?php
// approvals.php
$approvals = array_filter($_SESSION['heydream_data']['approvals'], fn($a) => $a['status'] === 'pending');
?>
<div class="section-header">
    <h2><i class="fas fa-clipboard-list"></i> Approval Queue</h2>
    <p>Review partnership service posts, packages, and customer reviews before they go live</p>
</div>
<?php if (empty($approvals)): ?>
    <div class="empty-message"><i class="fas fa-check-circle"></i> All items approved. Nothing pending.</div>
<?php else: foreach ($approvals as $item): ?>
<div class="approval-item">
    <div class="report-header">
        <span class="category-badge">📄 <?php echo $item['type']; ?></span>
        <span class="badge badge-open">Pending</span>
    </div>
    <div class="report-title"><?php echo escapeHtml($item['title']); ?></div>
    <div class="report-desc">
        <small>Partner: <?php echo escapeHtml($item['partner']); ?> | Created: <?php echo $item['createdAt']; ?></small><br>
        <?php echo escapeHtml($item['details']); ?>
    </div>
    <div class="action-buttons">
        <form method="POST"><input type="hidden" name="action" value="approve_item"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="redirect_page" value="approval"><button class="btn-primary"><i class="fas fa-check"></i> Approve & Publish</button></form>
        <form method="POST" onsubmit="return confirm('Reject this item?')"><input type="hidden" name="action" value="reject_item"><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><input type="hidden" name="redirect_page" value="approval"><button class="btn-outline"><i class="fas fa-ban"></i> Reject</button></form>
    </div>
</div>
<?php endforeach; endif; ?>