-- =====================================================================
-- HỆ THỐNG QUẢN LÝ PHÊ DUYỆT TÀI SẢN TRUYỀN THÔNG (MAKER-CHECKER + AI)
-- =====================================================================
-- Mục tiêu:
--  1. Phân tách vai trò: Maker tạo ấn phẩm, Checker duyệt, Admin quản lý.
--  2. Ứng dụng AI tự động chấm điểm trước khi Checker thẩm định.
--  3. 14 Business Rules được cài bằng Trigger để bảo vệ toàn vẹn dữ liệu.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS mas_project
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mas_project;

-- Vô hiệu hóa kiểm tra khóa ngoại tạm thời để dọn sạch các bảng cũ khi chạy lại
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs, check_results, asset_versions, assets, campaigns, users;
DROP FUNCTION IF EXISTS fn_role;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- PHẦN 1: DANH SÁCH 6 BẢNG DỮ LIỆU CHÍNH
-- =====================================================================

-- ---------------------------------------------------------------------
-- BẢNG 1: USERS (Quản lý tài khoản và phân quyền hệ thống)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Khóa chính, mã định danh người dùng
  username      VARCHAR(50)  NOT NULL UNIQUE,            -- Tên đăng nhập (duy nhất)
  email         VARCHAR(100) NOT NULL UNIQUE,            -- Địa chỉ email liên hệ (duy nhất)
  password_hash VARCHAR(255) NOT NULL,                   -- Mật khẩu đã được băm/mã hóa bảo mật
  role          ENUM('maker','checker','admin') NOT NULL,-- Vai trò: Maker (tạo), Checker (duyệt), Admin (quản trị)
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP -- Thời điểm tạo tài khoản
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- BẢNG 2: CAMPAIGNS (Quản lý chiến dịch truyền thông / marketing)
-- ---------------------------------------------------------------------
CREATE TABLE campaigns (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Khóa chính, mã chiến dịch
  name               VARCHAR(150) NOT NULL,                   -- Tên chiến dịch
  description        TEXT NULL,                               -- Mô tả chi tiết yêu cầu của chiến dịch
  deadline           DATETIME NULL,                           -- Thời hạn chót nộp ấn phẩm
  guideline_file_url VARCHAR(500) NULL,                       -- Đường dẫn file tài liệu hướng dẫn/quy chuẩn thiết kế
  status             ENUM('active','closed') NOT NULL DEFAULT 'active', -- Trạng thái chiến dịch (active: đang mở, closed: đã đóng)
  created_by         INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Người tạo chiến dịch (chỉ Admin)
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời gian khởi tạo chiến dịch
  CONSTRAINT fk_campaign_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- BẢNG 3: ASSETS (Quản lý ấn phẩm truyền thông: hình ảnh, video, banner...)
-- ---------------------------------------------------------------------
CREATE TABLE assets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Khóa chính, mã ấn phẩm
  campaign_id INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Thuộc chiến dịch nào
  title       VARCHAR(200) NOT NULL,                   -- Tiêu đề ấn phẩm truyền thông
  status      ENUM('draft','scoring','in_review','approved','rejected') 
              NOT NULL DEFAULT 'draft',                -- Vòng đời trạng thái: draft -> scoring -> in_review -> approved/rejected
  created_by  INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Maker tạo ra ấn phẩm này
  reviewed_by INT UNSIGNED NULL,                       -- Khóa ngoại: Checker hoặc Admin duyệt/từ chối ấn phẩm
  reviewed_at DATETIME NULL,                           -- Thời điểm phê duyệt hoặc từ chối
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời gian tạo ấn phẩm ban đầu
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Thời gian cập nhật gần nhất
  CONSTRAINT fk_asset_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
  CONSTRAINT fk_asset_creator  FOREIGN KEY (created_by)  REFERENCES users(id),
  CONSTRAINT fk_asset_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id),
  INDEX idx_asset_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- BẢNG 4: ASSET_VERSIONS (Lịch sử các phiên bản tệp tải lên mỗi lần cập nhật)
-- ---------------------------------------------------------------------
CREATE TABLE asset_versions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Khóa chính, mã phiên bản
  asset_id    INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Thuộc về ấn phẩm nào
  version_no  INT UNSIGNED NOT NULL DEFAULT 0,         -- Số thứ tự phiên bản (Trigger tự động tăng n+1: v1, v2...)
  file_url    VARCHAR(500) NOT NULL,                   -- Đường dẫn lưu trữ tệp trên máy chủ hoặc cloud
  file_type   VARCHAR(20)  NULL,                       -- Định dạng tệp tin (PNG, MP4, PDF...)
  uploaded_by INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Người thực hiện tải tệp lên
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời điểm tải lên
  CONSTRAINT fk_version_asset FOREIGN KEY (asset_id)    REFERENCES assets(id) ON DELETE CASCADE,
  CONSTRAINT fk_version_user  FOREIGN KEY (uploaded_by) REFERENCES users(id),
  UNIQUE KEY uq_asset_version (asset_id, version_no)   -- Một ấn phẩm không thể có 2 phiên bản trùng số thứ tự
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- BẢNG 5: CHECK_RESULTS (Kết quả chấm điểm của AI và đánh giá của Checker)
-- ---------------------------------------------------------------------
CREATE TABLE check_results (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Khóa chính, mã kết quả chấm
  version_id   INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Chấm cho phiên bản tệp nào
  checker_type ENUM('ai','human') NOT NULL DEFAULT 'ai',-- Chủ thể chấm: 'ai' (máy chấm) hoặc 'human' (người chấm)
  checker_id   INT UNSIGNED NULL,                       -- Khóa ngoại: Mã người chấm (NULL nếu là AI chấm)
  score        TINYINT UNSIGNED NOT NULL,               -- Thang điểm đánh giá từ 0 đến 100
  verdict      VARCHAR(12) GENERATED ALWAYS AS (        -- Phán quyết tự động sinh ra dựa trên điểm số:
                 CASE WHEN score <= 30 THEN 'fail'       -- <= 30 điểm: Không đạt (fail)
                      WHEN score <= 70 THEN 'needs_look' -- 31 - 70 điểm: Cần xem xét lại (needs_look)
                      ELSE 'pass' END                   -- > 70 điểm: Đạt yêu cầu (pass)
               ) STORED,
  reason       TEXT NULL,                               -- Lý do đánh giá hoặc mô tả chi tiết lỗi vi phạm
  source_quote TEXT NULL,                               -- Trích dẫn điều khoản quy chuẩn bị vi phạm trong Guideline
  raw_json     JSON NULL,                               -- Dữ liệu JSON gốc trả về từ công cụ AI
  is_override  BOOLEAN NOT NULL DEFAULT FALSE,          -- True nếu Checker ghi đè/chỉnh sửa lại quyết định của AI
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời gian ghi nhận kết quả đánh giá
  CONSTRAINT fk_result_version FOREIGN KEY (version_id) REFERENCES asset_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_result_checker FOREIGN KEY (checker_id) REFERENCES users(id),
  CONSTRAINT chk_score_range CHECK (score BETWEEN 0 AND 100) -- Bắt buộc điểm số phải nằm trong khoảng từ 0 đến 100
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- BẢNG 6: AUDIT_LOGS (Nhật ký kiểm toán bảo mật - Chỉ cho phép thêm mới)
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Khóa chính, mã dòng nhật ký
  user_id    INT UNSIGNED NULL,                          -- Khóa ngoại: Người thao tác (NULL nếu do hệ thống chạy tự động)
  asset_id   INT UNSIGNED NULL,                          -- Khóa ngoại: Ấn phẩm chịu tác động
  action     VARCHAR(50) NOT NULL,                       -- Hành động: upload, status_in_review, override...
  old_status VARCHAR(20) NULL,                           -- Trạng thái trước khi thay đổi
  new_status VARCHAR(20) NULL,                           -- Trạng thái sau khi thay đổi
  note       TEXT NULL,                                  -- Ghi chú chi tiết về sự kiện
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời điểm sự kiện diễn ra
  CONSTRAINT fk_log_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE SET NULL,
  CONSTRAINT fk_log_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
  INDEX idx_log_asset (asset_id),
  INDEX idx_log_time (created_at)
) ENGINE=InnoDB;

-- =====================================================================
-- PHẦN 2: CÁC QUY TẮC RÀNG BUỘC NGHIỆP VỤ (TRIGGERS & FUNCTION)
-- =====================================================================

-- HÀM TIỆN ÍCH: Lấy nhanh vai trò (role) của người dùng qua ID
DELIMITER $$
CREATE FUNCTION fn_role(p_user INT UNSIGNED) RETURNS VARCHAR(10)
READS SQL DATA
BEGIN
  DECLARE r VARCHAR(10);
  SELECT role INTO r FROM users WHERE id = p_user;
  RETURN r;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R1: Chỉ Admin mới có quyền tạo Chiến dịch mới
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_campaigns_bi BEFORE INSERT ON campaigns FOR EACH ROW
BEGIN
  IF fn_role(NEW.created_by) <> 'admin' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R1: Chỉ Admin mới được tạo Campaign';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R2 & R3: 
-- - Chỉ Maker hoặc Admin mới được quyền tạo Asset
-- - Asset chỉ được tạo trong Campaign đang mở (active) và luôn bắt đầu ở 'draft'
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_assets_bi BEFORE INSERT ON assets FOR EACH ROW
BEGIN
  DECLARE v_cstatus VARCHAR(10);
  IF fn_role(NEW.created_by) NOT IN ('maker','admin') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R2: Chỉ Maker hoặc Admin mới được tạo Asset';
  END IF;

  SELECT status INTO v_cstatus FROM campaigns WHERE id = NEW.campaign_id;
  IF v_cstatus <> 'active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R3: Chiến dịch đã đóng, không thể tạo thêm Asset';
  END IF;

  -- Mặc định khởi tạo luôn là draft, chưa có thông tin người duyệt
  SET NEW.status = 'draft', NEW.reviewed_by = NULL, NEW.reviewed_at = NULL;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R6, R7, R8, R9: Kiểm soát luồng trạng thái và người phê duyệt
-- - R6: Luồng bắt buộc: draft -> scoring -> in_review -> approved/rejected
-- - R7: Muốn vào 'in_review' thì bản mới nhất bắt buộc phải có kết quả chấm điểm
-- - R8: Phê duyệt/từ chối phải do Checker hoặc Admin thực hiện
-- - R9: Maker-Checker: Tuyệt đối không được tự duyệt ấn phẩm của chính mình
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_assets_bu BEFORE UPDATE ON assets FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN
    -- Kiểm tra luồng chuyển trạng thái hợp lệ
    IF NOT (   (OLD.status = 'draft'     AND NEW.status = 'scoring')
            OR (OLD.status = 'scoring'   AND NEW.status = 'in_review')
            OR (OLD.status = 'in_review' AND NEW.status IN ('approved','rejected'))
            OR (OLD.status = 'rejected'  AND NEW.status = 'draft')) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R6: Chuyển trạng thái không hợp lệ theo quy trình';
    END IF;

    -- Bắt buộc phiên bản tệp mới nhất phải có kết quả chấm trước khi vào in_review
    IF NEW.status = 'in_review' THEN
      IF NOT EXISTS (
        SELECT 1 FROM check_results cr
        JOIN asset_versions v ON v.id = cr.version_id
        WHERE v.asset_id = NEW.id
          AND v.version_no = (SELECT MAX(version_no) FROM asset_versions WHERE asset_id = NEW.id)
      ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R7: Phiên bản mới nhất chưa có kết quả chấm điểm';
      END IF;
    END IF;

    -- Kiểm tra điều kiện khi duyệt hoặc từ chối
    IF NEW.status IN ('approved','rejected') THEN
      IF NEW.reviewed_by IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R8: Phải có thông tin người duyệt (reviewed_by)';
      END IF;
      IF fn_role(NEW.reviewed_by) NOT IN ('checker','admin') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R8: Chỉ Checker hoặc Admin mới có quyền duyệt ấn phẩm';
      END IF;
      IF NEW.reviewed_by = NEW.created_by THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R9: Nguyên tắc kiểm soát chéo: Không được tự duyệt Asset của chính mình';
      END IF;
      SET NEW.reviewed_at = NOW();
    END IF;

    -- Nếu quay về draft để sửa lại thì xóa thông tin duyệt trước đó
    IF NEW.status = 'draft' THEN
      SET NEW.reviewed_by = NULL, NEW.reviewed_at = NULL;
    END IF;
  END IF;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R14: Tự động ghi nhật ký (Audit Log) khi trạng thái thay đổi
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_assets_au AFTER UPDATE ON assets FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN
    INSERT INTO audit_logs (user_id, asset_id, action, old_status, new_status)
    VALUES (COALESCE(@app_user_id, NEW.reviewed_by), NEW.id,
            CONCAT('status_', NEW.status), OLD.status, NEW.status);
  END IF;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R10: Ấn phẩm đã duyệt (approved) là bất biến, cấm xóa khỏi hệ thống
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_assets_bd BEFORE DELETE ON assets FOR EACH ROW
BEGIN
  IF OLD.status = 'approved' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R10: Ấn phẩm đã duyệt (approved) là dữ liệu lưu trữ chính thức, không được xóa';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R4 & R5: Kiểm soát tải tệp và tự động tăng số thứ tự phiên bản
-- - Chỉ tải lên khi ấn phẩm ở draft hoặc rejected, và chiến dịch còn hạn
-- - version_no tự động tăng n+1
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_versions_bi BEFORE INSERT ON asset_versions FOR EACH ROW
BEGIN
  DECLARE v_status   VARCHAR(12);
  DECLARE v_cstatus  VARCHAR(10);
  DECLARE v_deadline DATETIME;

  IF fn_role(NEW.uploaded_by) NOT IN ('maker','admin') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R2: Chỉ Maker hoặc Admin mới được phép tải tệp lên';
  END IF;

  SELECT a.status, c.status, c.deadline INTO v_status, v_cstatus, v_deadline
  FROM assets a JOIN campaigns c ON c.id = a.campaign_id WHERE a.id = NEW.asset_id;

  IF v_status NOT IN ('draft','rejected') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R4: Chỉ được tải tệp lên khi Asset ở trạng thái draft hoặc rejected';
  END IF;
  IF v_cstatus <> 'active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R4: Chiến dịch đã đóng, không thể tải thêm tệp';
  END IF;
  IF v_deadline IS NOT NULL AND v_deadline < NOW() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R4: Đã quá hạn chót (deadline) của chiến dịch';
  END IF;

  -- Tự động tính số thứ tự phiên bản kế tiếp (version_no = max + 1)
  SET NEW.version_no = COALESCE(
    (SELECT MAX(version_no) FROM asset_versions WHERE asset_id = NEW.asset_id), 0) + 1;
END$$

-- ---------------------------------------------------------------------
-- Tự động đưa Asset về 'draft' khi tải lên phiên bản mới và ghi nhật ký
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_versions_ai AFTER INSERT ON asset_versions FOR EACH ROW
BEGIN
  UPDATE assets SET status = 'draft' WHERE id = NEW.asset_id AND status = 'rejected';
  INSERT INTO audit_logs (user_id, asset_id, action, note)
  VALUES (NEW.uploaded_by, NEW.asset_id, 'upload', CONCAT('Phiên bản số ', NEW.version_no));
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R11, R12, R13: Ràng buộc khi lưu kết quả chấm điểm
-- - R13: Chỉ chấm điểm cho phiên bản tệp mới nhất
-- - R11: AI chỉ chấm khi Asset ở trạng thái scoring; checker_id phải để trống (NULL)
-- - R12: Người chỉ chấm khi Asset ở in_review; không tự chấm bài của mình; ghi đè phải có điểm AI trước
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_results_bi BEFORE INSERT ON check_results FOR EACH ROW
BEGIN
  DECLARE v_asset   INT UNSIGNED;
  DECLARE v_vno     INT UNSIGNED;
  DECLARE v_status  VARCHAR(12);
  DECLARE v_creator INT UNSIGNED;

  SELECT v.asset_id, v.version_no, a.status, a.created_by
  INTO v_asset, v_vno, v_status, v_creator
  FROM asset_versions v JOIN assets a ON a.id = v.asset_id WHERE v.id = NEW.version_id;

  -- Bắt buộc chỉ chấm cho phiên bản mới nhất
  IF v_vno <> (SELECT MAX(version_no) FROM asset_versions WHERE asset_id = v_asset) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R13: Chỉ được chấm điểm cho phiên bản tệp mới nhất';
  END IF;

  -- Kiểm tra logic nếu chủ thể chấm là AI
  IF NEW.checker_type = 'ai' THEN
    IF NEW.checker_id IS NOT NULL OR NEW.is_override THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R11: Kết quả do AI chấm: checker_id phải là NULL và không được đánh dấu ghi đè';
    END IF;
    IF v_status <> 'scoring' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R11: AI chỉ được thực hiện chấm khi Asset ở trạng thái scoring';
    END IF;
  ELSE
    -- Kiểm tra logic nếu chủ thể chấm là Người (Checker/Admin)
    IF NEW.checker_id IS NULL OR fn_role(NEW.checker_id) NOT IN ('checker','admin') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Chỉ Checker hoặc Admin mới có quyền chấm điểm thủ công';
    END IF;
    IF NEW.checker_id = v_creator THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Người kiểm duyệt không được tự chấm điểm Asset do mình tạo ra';
    END IF;
    IF v_status <> 'in_review' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Người thẩm định chỉ được chấm khi Asset đang ở trạng thái in_review';
    END IF;
    IF NEW.is_override AND NOT EXISTS (
         SELECT 1 FROM check_results WHERE version_id = NEW.version_id AND checker_type = 'ai') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Hành động ghi đè (override) yêu cầu phải có kết quả chấm từ AI trước đó';
    END IF;
  END IF;
END$$

-- Tự động ghi nhật ký hệ thống sau khi lưu kết quả chấm điểm
CREATE TRIGGER trg_results_ai AFTER INSERT ON check_results FOR EACH ROW
BEGIN
  INSERT INTO audit_logs (user_id, asset_id, action, note)
  SELECT NEW.checker_id, v.asset_id,
         IF(NEW.is_override, 'override', CONCAT('scored_', NEW.checker_type)),
         CONCAT('Điểm số: ', NEW.score, ' | Kết luận: ', NEW.verdict)
  FROM asset_versions v WHERE v.id = NEW.version_id;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R13: Không cho phép sửa kết quả đã chấm (phải thêm dòng mới nếu muốn ghi đè)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_results_bu BEFORE UPDATE ON check_results FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R13: Kết quả chấm điểm không được phép sửa, hãy thêm dòng mới với cờ ghi đè (is_override)';
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R14: Nhật ký kiểm toán là bất biến (Cấm hoàn toàn việc sửa hoặc xóa)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_logs_bu BEFORE UPDATE ON audit_logs FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R14: Nhật ký kiểm toán (Audit log) không được phép chỉnh sửa';
END$$

CREATE TRIGGER trg_logs_bd BEFORE DELETE ON audit_logs FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R14: Nhật ký kiểm toán (Audit log) không được phép xóa khỏi hệ thống';
END$$

DELIMITER ;
