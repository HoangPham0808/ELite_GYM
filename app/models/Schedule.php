<?php

class Schedule extends Model
{
    private function hasColumn(string $table, string $column): bool
    {
        $table = $this->db->real_escape_string($table);
        $column = $this->db->real_escape_string($column);
        $result = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $result && $result->num_rows > 0;
    }

    private function buildWhereClause(array $conditions): string
    {
        return implode(' AND ', $conditions) ?: '1=1';
    }

    public function getAdminStats(): array
    {
        $total = (int)$this->db->query("SELECT COUNT(*) AS c FROM TrainingClass")->fetch_assoc()['c'];
        $today = (int)$this->db->query("SELECT COUNT(*) AS c FROM TrainingClass WHERE DATE(start_time) = CURDATE()")->fetch_assoc()['c'];
        $week = (int)$this->db->query("SELECT COUNT(*) AS c FROM TrainingClass WHERE start_time BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)")->fetch_assoc()['c'];
        $regs = (int)$this->db->query("SELECT COUNT(*) AS c FROM ClassRegistration")->fetch_assoc()['c'];
        $trainers = (int)$this->db->query("SELECT COUNT(DISTINCT trainer_id) AS c FROM TrainingClass WHERE trainer_id IS NOT NULL AND start_time <= NOW() AND (end_time IS NULL OR end_time >= NOW())")->fetch_assoc()['c'];
        $upcoming = (int)$this->db->query("SELECT COUNT(*) AS c FROM TrainingClass WHERE start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['c'];

        return [
            'total' => $total,
            'today' => $today,
            'week' => $week,
            'registered' => $regs,
            'trainers' => $trainers,
            'upcoming' => $upcoming,
        ];
    }

    public function getSchedulesList(int $page, int $limit, string $search, string $trainer_id, string $room_id, string $from, string $to, string $class_type = ''): array
    {
        $offset = ($page - 1) * $limit;
        $where = ['1=1'];
        $params = [];
        $types = '';

        if ($search !== '') {
            $where[] = '(tc.class_name LIKE ? OR gr.room_name LIKE ? OR e.full_name LIKE ?)';
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        if ($trainer_id !== '') {
            $where[] = 'tc.trainer_id = ?';
            $params[] = (int)$trainer_id;
            $types .= 'i';
        }

        if ($room_id !== '') {
            $where[] = 'tc.room_id = ?';
            $params[] = (int)$room_id;
            $types .= 'i';
        }

        if ($class_type !== '') {
            $where[] = 'pt.type_name LIKE ?';
            $like = "%{$class_type}%";
            $params[] = $like;
            $types .= 's';
        }

        if ($from !== '') {
            $where[] = 'tc.start_time >= ?';
            $params[] = $from;
            $types .= 's';
        }

        if ($to !== '') {
            $where[] = 'tc.start_time <= ?';
            $params[] = $to;
            $types .= 's';
        }

        $whereSql = $this->buildWhereClause($where);

        $countSql = "SELECT COUNT(DISTINCT tc.class_id) AS total
            FROM TrainingClass tc
            LEFT JOIN Employee e ON e.employee_id = tc.trainer_id
            LEFT JOIN GymRoom gr ON gr.room_id = tc.room_id
            LEFT JOIN PackageType pt ON pt.type_id = gr.package_type_id
            WHERE {$whereSql}";

        $countStmt = $this->db->prepare($countSql);
        if ($countStmt && $types !== '') {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        $sql = "SELECT
                tc.class_id,
                tc.class_name,
                tc.trainer_id,
                e.full_name AS trainer_name,
                tc.room_id,
                gr.room_name,
                COALESCE(pt.type_name, 'Basic') AS package_name,
                pt.type_name AS room_type_name,
                pt.sort_order AS room_sort_order,
                pt.color_code AS room_type_color,
                tc.start_time,
                tc.end_time,
                COALESCE(gr.capacity, 20) AS max_capacity,
                (SELECT COUNT(*) FROM ClassRegistration cr WHERE cr.class_id = tc.class_id) AS current_enrollment
            FROM TrainingClass tc
            LEFT JOIN Employee e ON e.employee_id = tc.trainer_id
            LEFT JOIN GymRoom gr ON gr.room_id = tc.room_id
            LEFT JOIN PackageType pt ON pt.type_id = gr.package_type_id
            WHERE {$whereSql}
            ORDER BY tc.start_time ASC
            LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $bindParams = $params;
        $bindTypes = $types . 'ii';
        $bindParams[] = $limit;
        $bindParams[] = $offset;
        if ($stmt) {
            if ($bindParams) {
                $stmt->bind_param($bindTypes, ...$bindParams);
            }
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            return ['data' => [], 'total' => 0, 'totalPages' => 1];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return [
            'data' => $rows,
            'total' => $total,
            'totalPages' => (int)max(1, ceil($total / $limit)),
        ];
    }

    public function getWeekSchedules(string $week_start): array
    {
        $start = date('Y-m-d', strtotime($week_start));
        $end = date('Y-m-d', strtotime($week_start . ' +6 days'));
        $sql = "SELECT
                tc.class_id,
                tc.class_name,
                tc.trainer_id,
                e.full_name AS trainer_name,
                tc.room_id,
                gr.room_name,
                COALESCE(pt.type_name, 'Basic') AS package_name,
                pt.type_name AS room_type_name,
                pt.sort_order AS room_sort_order,
                pt.color_code AS room_type_color,
                tc.start_time,
                tc.end_time,
                COALESCE(gr.capacity, 20) AS max_capacity,
                (SELECT COUNT(*) FROM ClassRegistration cr WHERE cr.class_id = tc.class_id) AS current_enrollment
            FROM TrainingClass tc
            LEFT JOIN Employee e ON e.employee_id = tc.trainer_id
            LEFT JOIN GymRoom gr ON gr.room_id = tc.room_id
            LEFT JOIN PackageType pt ON pt.type_id = gr.package_type_id
            WHERE tc.start_time BETWEEN ? AND ?
            ORDER BY tc.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $startTime = $start . ' 00:00:00';
        $endTime = $end . ' 23:59:59';
        $stmt->bind_param('ss', $startTime, $endTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    public function getScheduleDetail(int $id): ?array
    {
        $sql = "SELECT
                tc.*,
                e.full_name AS trainer_name,
                gr.room_name,
                COALESCE(pt.type_name, 'Basic') AS package_name,
                pt.type_name AS room_type_name,
                pt.sort_order AS room_sort_order,
                pt.color_code AS room_type_color
            FROM TrainingClass tc
            LEFT JOIN Employee e ON e.employee_id = tc.trainer_id
            LEFT JOIN GymRoom gr ON gr.room_id = tc.room_id
            LEFT JOIN PackageType pt ON pt.type_id = gr.package_type_id
            WHERE tc.class_id = ?
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $class = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$class) {
            return null;
        }

        $sql2 = "SELECT
                cr.class_registration_id,
                c.customer_id,
                c.full_name,
                COALESCE(c.username, '') AS username,
                c.phone,
                mp.plan_name AS package_name,
                cr.registration_time
            FROM ClassRegistration cr
            JOIN Customer c ON c.customer_id = cr.customer_id
            LEFT JOIN MembershipRegistration mr ON mr.customer_id = c.customer_id AND mr.status = 'active' AND mr.end_date >= CURDATE()
            LEFT JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
            WHERE cr.class_id = ?
            ORDER BY cr.class_registration_id ASC";

        $stmt2 = $this->db->prepare($sql2);
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $customers = [];
        $result2 = $stmt2->get_result();
        while ($row = $result2->fetch_assoc()) {
            $customers[] = $row;
        }
        $stmt2->close();

        return ['class' => $class, 'enrolled_customers' => $customers];
    }

    public function addSchedule(string $class_name, ?int $trainer_id, ?int $room_id, string $start_time, ?string $end_time, ?int $max_capacity = null): int|false
    {
        $fields = ['class_name', 'start_time'];
        $placeholders = ['?', '?'];
        $params = [$class_name, $start_time];
        $types = 'ss';

        if ($trainer_id !== null) {
            $fields[] = 'trainer_id';
            $placeholders[] = '?';
            $params[] = $trainer_id;
            $types .= 'i';
        }

        if ($room_id !== null) {
            $fields[] = 'room_id';
            $placeholders[] = '?';
            $params[] = $room_id;
            $types .= 'i';
        }

        if ($end_time !== null) {
            $fields[] = 'end_time';
            $placeholders[] = '?';
            $params[] = $end_time;
            $types .= 's';
        }

        if ($max_capacity !== null && $this->hasColumn('TrainingClass', 'max_capacity')) {
            $fields[] = 'max_capacity';
            $placeholders[] = '?';
            $params[] = $max_capacity;
            $types .= 'i';
        }

        $sql = 'INSERT INTO TrainingClass (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $id = $ok ? (int)$this->db->insert_id : false;
        $stmt->close();
        return $id;
    }

    public function updateSchedule(int $id, string $class_name, ?int $trainer_id, ?int $room_id, string $start_time, ?string $end_time, ?int $max_capacity = null): bool
    {
        $fields = ['class_name = ?', 'start_time = ?'];
        $params = [$class_name, $start_time];
        $types = 'ss';

        if ($trainer_id !== null) {
            $fields[] = 'trainer_id = ?';
            $params[] = $trainer_id;
            $types .= 'i';
        } else {
            $fields[] = 'trainer_id = NULL';
        }

        if ($room_id !== null) {
            $fields[] = 'room_id = ?';
            $params[] = $room_id;
            $types .= 'i';
        } else {
            $fields[] = 'room_id = NULL';
        }

        if ($end_time !== null) {
            $fields[] = 'end_time = ?';
            $params[] = $end_time;
            $types .= 's';
        } else {
            $fields[] = 'end_time = NULL';
        }

        if ($max_capacity !== null && $this->hasColumn('TrainingClass', 'max_capacity')) {
            $fields[] = 'max_capacity = ?';
            $params[] = $max_capacity;
            $types .= 'i';
        }

        $sql = 'UPDATE TrainingClass SET ' . implode(', ', $fields) . ' WHERE class_id = ?';
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteSchedule(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM TrainingClass WHERE class_id = ?');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getCustomerInfoByAccount(int $account_id): ?int
    {
        $stmt = $this->db->prepare('SELECT customer_id FROM Customer WHERE account_id = ? LIMIT 1');
        $stmt->bind_param('i', $account_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['customer_id'] : null;
    }

    public function getClassInfo(int $class_id): ?array
    {
        $sql = "SELECT
                tc.*,
                e.full_name AS trainer_name,
                gr.room_name,
                COALESCE(pt.type_name, 'Basic') AS package_name,
                pt.type_name AS room_type_name,
                pt.sort_order AS room_sort_order,
                pt.color_code AS room_type_color
            FROM TrainingClass tc
            LEFT JOIN Employee e ON e.employee_id = tc.trainer_id
            LEFT JOIN GymRoom gr ON gr.room_id = tc.room_id
            LEFT JOIN PackageType pt ON pt.type_id = gr.package_type_id
            WHERE tc.class_id = ?
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $class_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function getCustomerActivePackageSortOrder(int $customer_id): ?int
    {
        $sql = "SELECT pt.sort_order
            FROM MembershipRegistration mr
            JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
            LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
            WHERE mr.customer_id = ?
              AND mr.status = 'active'
              AND mr.end_date >= CURDATE()
            ORDER BY pt.sort_order DESC
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['sort_order'] : null;
    }

    public function checkClassRegistrationExists(int $class_id, int $customer_id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM ClassRegistration WHERE class_id = ? AND customer_id = ? LIMIT 1');
        $stmt->bind_param('ii', $class_id, $customer_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function checkTimeSlotConflict(int $customer_id, string $start_time): ?array
    {
        $stmt = $this->db->prepare("SELECT tc.class_id, tc.start_time AS time, DATE(tc.start_time) AS date
            FROM ClassRegistration cr
            JOIN TrainingClass tc ON tc.class_id = cr.class_id
            WHERE cr.customer_id = ? AND tc.start_time = ?
            LIMIT 1");
        $stmt->bind_param('is', $customer_id, $start_time);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function registerClass(int $class_id, int $customer_id): bool
    {
        if ($this->checkClassRegistrationExists($class_id, $customer_id)) {
            return false;
        }
        $stmt = $this->db->prepare('INSERT INTO ClassRegistration (class_id, customer_id, registration_time) VALUES (?, ?, NOW())');
        $stmt->bind_param('ii', $class_id, $customer_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function cancelClass(int $class_id, int $customer_id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM ClassRegistration WHERE class_id = ? AND customer_id = ?');
        $stmt->bind_param('ii', $class_id, $customer_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function cancelRegistrationByClassAndCustomer(int $class_id, int $customer_id): bool
    {
        return $this->cancelClass($class_id, $customer_id);
    }

    public function addRegistration(int $class_id, int $customer_id): bool
    {
        return $this->registerClass($class_id, $customer_id);
    }

    public function assignTrainer(int $class_id, int $trainer_id): bool
    {
        $stmt = $this->db->prepare('UPDATE TrainingClass SET trainer_id = ? WHERE class_id = ?');
        $stmt->bind_param('ii', $trainer_id, $class_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function unassignTrainer(int $class_id, int $trainer_id): bool
    {
        $stmt = $this->db->prepare('UPDATE TrainingClass SET trainer_id = NULL WHERE class_id = ? AND trainer_id = ?');
        $stmt->bind_param('ii', $class_id, $trainer_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getClassInfoForAI(int $class_id): ?array
    {
        $class = $this->getClassInfo($class_id);
        if (!$class) {
            return null;
        }

        $equipment = 'thiết bị cơ bản';
        if (!empty($class['room_id'])) {
            $stmt = $this->db->prepare('SELECT GROUP_CONCAT(equipment_name SEPARATOR ", ") AS equipment_list FROM Equipment WHERE room_id = ?');
            $stmt->bind_param('i', $class['room_id']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($row['equipment_list'])) {
                $equipment = $row['equipment_list'];
            }
        }
        $class['equipment_list'] = $equipment;
        return $class;
    }

    public function getTrainers(): array
    {
        $res = $this->db->query('SELECT employee_id, full_name FROM Employee ORDER BY full_name ASC');
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function getRooms(): array
    {
        $res = $this->db->query('SELECT room_id, room_name FROM GymRoom ORDER BY room_name ASC');
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function getRoomInfo(int $room_id): ?array
    {
        $stmt = $this->db->prepare('SELECT room_name, open_time, close_time FROM GymRoom WHERE room_id = ? LIMIT 1');
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $room = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $room ?: null;
    }

    public function getCustomersSearch(string $search, string $packageRequirement = ''): array
    {
        $where = ['1=1'];
        $params = [];
        $types = '';

        if ($search !== '') {
            $where[] = "(c.full_name LIKE ? OR c.phone LIKE ? OR COALESCE(c.username, '') LIKE ?)";
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        if ($packageRequirement !== '') {
            $where[] = '(mp.plan_name LIKE ? OR pt.type_name LIKE ?)';
            $pkg = "%{$packageRequirement}%";
            $params[] = $pkg;
            $params[] = $pkg;
            $types .= 'ss';
        }

        $sql = "SELECT DISTINCT c.customer_id, c.full_name, COALESCE(c.username, '') AS username, c.phone,
                COALESCE(mp.plan_name, '') AS package_name
            FROM Customer c
            LEFT JOIN MembershipRegistration mr ON mr.customer_id = c.customer_id AND mr.status = 'active' AND mr.end_date >= CURDATE()
            LEFT JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
            LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
            WHERE " . $this->buildWhereClause($where) . "
            ORDER BY c.full_name ASC
            LIMIT 25";

        $stmt = $this->db->prepare($sql);
        if ($stmt && $types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    public function checkRoomConflict(int $room_id, string $start_time, ?string $end_time, ?int $excludeClassId = null): ?array
    {
        $query = "SELECT class_id, class_name, start_time, end_time FROM TrainingClass WHERE room_id = ?";
        $params = [$room_id];
        $types = 'i';

        if ($excludeClassId !== null) {
            $query .= ' AND class_id != ?';
            $types .= 'i';
            $params[] = $excludeClassId;
        }

        if ($end_time !== null) {
            $query .= ' AND start_time < ? AND (end_time IS NULL OR end_time > ?)';
            $types .= 'ss';
            $params[] = $end_time;
            $params[] = $start_time;
        } else {
            $query .= ' AND ((start_time <= ? AND (end_time IS NULL OR end_time > ?)) OR (start_time >= ? AND (end_time IS NULL OR start_time < ?)))';
            $types .= 'ssss';
            $params[] = $start_time;
            $params[] = $start_time;
            $params[] = $start_time;
            $params[] = $start_time;
        }

        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function checkTrainerConflict(int $trainer_id, string $class_date, string $start_time, ?string $end_time, ?int $excludeClassId = null): ?array
    {
        $query = "SELECT class_id, class_name, start_time, end_time FROM TrainingClass WHERE trainer_id = ? AND DATE(start_time) = ?";
        $params = [$trainer_id, $class_date];
        $types = 'is';

        if ($excludeClassId !== null) {
            $query .= ' AND class_id != ?';
            $types .= 'i';
            $params[] = $excludeClassId;
        }

        if ($end_time !== null) {
            $query .= ' AND start_time < ? AND (end_time IS NULL OR end_time > ?)';
            $types .= 'ss';
            $params[] = $end_time;
            $params[] = $start_time;
        } else {
            $query .= ' AND ((start_time <= ? AND (end_time IS NULL OR end_time > ?)) OR (start_time >= ? AND (end_time IS NULL OR start_time < ?)))';
            $types .= 'ssss';
            $params[] = $start_time;
            $params[] = $start_time;
            $params[] = $start_time;
            $params[] = $start_time;
        }

        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
