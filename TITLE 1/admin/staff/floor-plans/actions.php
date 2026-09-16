<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['sfp_action'] ?? '';
    try {
        if ($action === 'upload') {
            if (empty($_FILES['image']['name'])) throw new RuntimeException('Choose an image.');
            $title = trim($_POST['title'] ?? '') ?: 'Campus Map';
            if (!is_dir($orgDir)) mkdir($orgDir, 0775, true);
            $size = @getimagesize($_FILES['image']['tmp_name']);
            $w = (int) ($size[0] ?? 1200); $h = (int) ($size[1] ?? 900);
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)) ?: 'jpg';
            $name = random_token(6) . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $orgDir . '/' . $name);
            $newPlanId = crud()->insert('floor_plans', [
                'institution_id' => $iid, 'title' => $title, 'image_path' => 'assets/floorplans/' . $name,
                'original_width' => $w, 'original_height' => $h, 'aspect_ratio' => $w / max(1, $h),
                'object_fit' => 'contain', 'created_by' => $me,
            ]);
            audit('floor_plans.create', 'content', 'floor_plan', $newPlanId);
            flash('success', 'Floor plan uploaded.');
            redirect('admin/staff/floor-plans');
        }
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = crud()->raw('SELECT image_path FROM floor_plans WHERE id=:id AND institution_id=:iid LIMIT 1', ['id' => $id, 'iid' => $iid])->fetch();
            $img = $row['image_path'] ?? null;
            crud()->delete('floor_plans', ['id' => $id, 'institution_id' => $iid]);
            if ($img && is_file(ROOT_PATH . '/' . ltrim($img, '/'))) unlink(ROOT_PATH . '/' . ltrim($img, '/'));
            flash('success', 'Floor plan removed.');
            redirect('admin/staff/floor-plans');
        }
        if ($action === 'marker-add') {
            $label = trim($_POST['label'] ?? '');
            if ($label === '') throw new RuntimeException('Marker label required.');
            crud()->insert('floor_plan_markers', [
                'institution_id' => $iid, 'floor_plan_id' => (int) ($_POST['plan_id'] ?? 0), 'label' => $label,
                'x_percent' => (float) ($_POST['x'] ?? 50), 'y_percent' => (float) ($_POST['y'] ?? 50),
                'size_percent' => 4, 'popup_title' => trim($_POST['popup_title'] ?? '') ?: null,
                'popup_html' => trim($_POST['popup_html'] ?? '') ?: null, 'sort_order' => 0,
            ]);
            flash('success', 'Marker added.');
            redirect('admin/staff/floor-plans?plan=' . (int) ($_POST['plan_id'] ?? 0));
        }
        if ($action === 'marker-save') {
            $id = (int) ($_POST['id'] ?? 0);
            crud()->update('floor_plan_markers',
                [
                    'x_percent' => max(0, min(100, (float) ($_POST['x'] ?? 0))),
                    'y_percent' => max(0, min(100, (float) ($_POST['y'] ?? 0))),
                    'label' => trim($_POST['label'] ?? '') ?: 'Marker',
                    'popup_title' => trim($_POST['popup_title'] ?? '') ?: null,
                    'popup_html' => trim($_POST['popup_html'] ?? '') ?: null,
                ],
                ['id' => $id, 'institution_id' => $iid]
            );
            flash('success', 'Marker saved.');
            redirect('admin/staff/floor-plans?plan=' . (int) ($_POST['plan_id'] ?? 0));
        }
        if ($action === 'marker-delete') {
            crud()->delete('floor_plan_markers', ['id' => (int) ($_POST['id'] ?? 0), 'institution_id' => $iid]);
            flash('success', 'Marker removed.');
            redirect('admin/staff/floor-plans?plan=' . (int) ($_POST['plan_id'] ?? 0));
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('admin/staff/floor-plans' . ($planId ? '?plan=' . $planId : ''));
    }
}
