-- =====================================================================
-- HỆ THỐNG QUẢN LÝ PHÊ DUYỆT TÀI SẢN TRUYỀN THÔNG (MAKER-CHECKER + AI)
-- =====================================================================
-- Mục tiêu:
--  1. Phân tách vai trò: Maker tạo ấn phẩm, Checker duyệt, Admin quản lý và phát hành.
--  2. Ứng dụng AI tự động chấm điểm (3 tiêu chí) trước khi Checker thẩm định.
--  3. 17 Business Rules được cài bằng Trigger để bảo vệ toàn vẹn dữ liệu.
--
-- Điểm khác biệt so với bản cũ:
--  - Trạng thái, người duyệt, thời điểm duyệt nằm ở ASSET_VERSIONS (mỗi phiên bản giữ
--    lịch sử duyệt riêng, phiên bản mới không ghi đè thông tin của phiên bản cũ).
--  - Thêm trạng thái 'released' (Admin phát hành, sau đó phiên bản bị khóa).
--  - CHECK_RESULTS chấm theo từng tiêu chí (criterion), mỗi tiêu chí một dòng.
--  - AUDIT_LOGS ghi rõ phiên bản (version_id), prompt_version, model_version.
--  - Phân quyền theo từng chiến dịch qua bảng CAMPAIGN_MEMBERS.
-- =====================================================================

-- Cố định bảng mã và collation của kết nối. Trigger/hàm ghi nhớ collation lúc tạo,
-- nên phải trùng với collation của bảng, nếu không sẽ báo lỗi "Illegal mix of collations".
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS mas_project
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mas_project;

-- Vô hiệu hóa kiểm tra khóa ngoại tạm thời để dọn sạch các bảng cũ khi chạy lại
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs, check_results, asset_versions, assets, campaign_members, campaigns, users;
DROP FUNCTION IF EXISTS fn_role;
DROP FUNCTION IF EXISTS fn_campaign_role;
DROP FUNCTION IF EXISTS fn_version_has_fail;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- PHẦN 1: DANH SÁCH 7 BẢNG DỮ LIỆU CHÍNH
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
  owner_id           INT UNSIGNED NULL,                       -- Khóa ngoại: Người phụ trách chiến dịch (mặc định là người tạo)
  created_by         INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Người tạo chiến dịch (chỉ Admin)
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời gian khởi tạo chiến dịch
  CONSTRAINT fk_campaign_user  FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_campaign_owner FOREIGN KEY (owner_id)   REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- BẢNG 3: CAMPAIGN_MEMBERS (Phân quyền Maker/Checker theo từng chiến dịch)
-- ---------------------------------------------------------------------
CREATE TABLE campaign_members (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Khóa chính
  campaign_id INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Chiến dịch
  user_id     INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Thành viên được gán
  role        ENUM('maker','checker') NOT NULL,        -- Vai trò trong chiến dịch này (phải trùng vai trò tài khoản)
  CONSTRAINT fk_member_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_user     FOREIGN KEY (user_id)     REFERENCES users(id),
  UNIQUE KEY uq_member (campaign_id, user_id)          -- Một người chỉ có 1 vai trò trong 1 chiến dịch
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- BẢNG 4: ASSETS (Quản lý ấn phẩm truyền thông: hình ảnh, PDF, banner...)
-- Lưu ý: KHÔNG còn status/reviewed_by/reviewed_at, các thông tin này nằm ở asset_versions
-- ---------------------------------------------------------------------
CREATE TABLE assets (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Khóa chính, mã ấn phẩm
  campaign_id        INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Thuộc chiến dịch nào (không đổi được)
  title              VARCHAR(200) NOT NULL,                   -- Tiêu đề ấn phẩm truyền thông
  current_version_id INT UNSIGNED NULL,                       -- Khóa ngoại: Phiên bản mới nhất (trigger tự cập nhật)
  created_by         INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Maker tạo ra ấn phẩm này
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời gian tạo ấn phẩm ban đầu
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Thời gian cập nhật gần nhất
  CONSTRAINT fk_asset_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
  CONSTRAINT fk_asset_creator  FOREIGN KEY (created_by)  REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- BẢNG 5: ASSET_VERSIONS (Các phiên bản tệp; mỗi phiên bản có trạng thái và lịch sử duyệt riêng)
-- ---------------------------------------------------------------------
CREATE TABLE asset_versions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Khóa chính, mã phiên bản
  asset_id    INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Thuộc về ấn phẩm nào
  version_no  INT UNSIGNED NOT NULL DEFAULT 0,         -- Số thứ tự phiên bản (Trigger tự động tăng n+1: v1, v2...)
  file_url    VARCHAR(500) NOT NULL,                   -- Đường dẫn tệp (không sửa được sau khi tạo)
  file_type   VARCHAR(20)  NULL,                       -- Định dạng tệp tin (PNG, JPG, PDF...)
  status      ENUM('draft','scoring','in_review','approved','rejected','released')
              NOT NULL DEFAULT 'draft',                -- Vòng đời: draft -> scoring -> in_review -> approved/rejected; approved -> released
  uploaded_by INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Người thực hiện tải tệp lên
  reviewed_by INT UNSIGNED NULL,                       -- Khóa ngoại: Checker duyệt/từ chối phiên bản này
  reviewed_at DATETIME NULL,                           -- Thời điểm phê duyệt hoặc từ chối
  review_note TEXT NULL,                               -- Lý do từ chối, hoặc lý do duyệt dù có tiêu chí fail
  released_by INT UNSIGNED NULL,                       -- Khóa ngoại: Admin phát hành
  released_at DATETIME NULL,                           -- Thời điểm phát hành (sau đó phiên bản bị khóa)
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời điểm tải lên
  CONSTRAINT fk_version_asset    FOREIGN KEY (asset_id)    REFERENCES assets(id) ON DELETE CASCADE,
  CONSTRAINT fk_version_user     FOREIGN KEY (uploaded_by) REFERENCES users(id),
  CONSTRAINT fk_version_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id),
  CONSTRAINT fk_version_releaser FOREIGN KEY (released_by) REFERENCES users(id),
  UNIQUE KEY uq_asset_version (asset_id, version_no),  -- Một ấn phẩm không thể có 2 phiên bản trùng số thứ tự
  INDEX idx_version_status (status)
) ENGINE=InnoDB;

-- Khóa ngoại của assets trỏ sang asset_versions (tạo sau vì hai bảng tham chiếu vòng)
ALTER TABLE assets
  ADD CONSTRAINT fk_asset_current_version FOREIGN KEY (current_version_id)
  REFERENCES asset_versions(id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- BẢNG 6: CHECK_RESULTS (Kết quả chấm của AI và Checker, MỖI TIÊU CHÍ MỘT DÒNG)
-- Một phiên bản được AI chấm sẽ có 3 dòng: foreign_logo, guideline, mas_rule
-- ---------------------------------------------------------------------
CREATE TABLE check_results (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Khóa chính, mã kết quả chấm
  version_id     INT UNSIGNED NOT NULL,                   -- Khóa ngoại: Chấm cho phiên bản tệp nào
  criterion      ENUM('foreign_logo','guideline','mas_rule') NOT NULL, -- Tiêu chí: logo ngoại lai / guideline / luật MAS
  checker_type   ENUM('ai','human') NOT NULL DEFAULT 'ai',-- Chủ thể chấm: 'ai' (máy chấm) hoặc 'human' (người chấm)
  checker_id     INT UNSIGNED NULL,                       -- Khóa ngoại: Mã người chấm (NULL nếu là AI chấm)
  score          TINYINT UNSIGNED NOT NULL,               -- Điểm SỐ NGUYÊN từ 0 đến 100
  verdict        VARCHAR(12) GENERATED ALWAYS AS (        -- Phán quyết tự động sinh ra dựa trên điểm số:
                   CASE WHEN score <= 30 AND source_quote IS NOT NULL AND TRIM(source_quote) <> '' THEN 'fail'
                        WHEN score <= 30 THEN 'needs_look' -- <= 30 nhưng thiếu trích dẫn thì hạ xuống needs_look (chống ảo giác)
                        WHEN score <= 70 THEN 'needs_look' -- 31 - 70 điểm: Cần xem xét lại
                        ELSE 'pass' END                    -- 71 - 100 điểm: Đạt yêu cầu
                 ) STORED,
  reason         TEXT NULL,                               -- Lý do đánh giá hoặc mô tả chi tiết lỗi vi phạm
  source_quote   TEXT NULL,                               -- Trích dẫn điều khoản bị vi phạm (bắt buộc để có verdict fail)
  raw_json       JSON NULL,                               -- Dữ liệu JSON gốc trả về từ công cụ AI
  is_override    BOOLEAN NOT NULL DEFAULT FALSE,          -- True nếu Checker ghi đè/chỉnh sửa lại quyết định của AI
  prompt_version VARCHAR(50) NULL,                        -- Phiên bản prompt đã dùng (AI bắt buộc có)
  model_version  VARCHAR(50) NULL,                        -- Phiên bản model đã dùng (AI bắt buộc có)
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời gian ghi nhận kết quả đánh giá
  CONSTRAINT fk_result_version FOREIGN KEY (version_id) REFERENCES asset_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_result_checker FOREIGN KEY (checker_id) REFERENCES users(id),
  CONSTRAINT chk_score_range CHECK (score BETWEEN 0 AND 100), -- Bắt buộc điểm số phải nằm trong khoảng từ 0 đến 100
  INDEX idx_result_version (version_id, criterion)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- BẢNG 7: AUDIT_LOGS (Nhật ký kiểm toán bảo mật - Chỉ cho phép thêm mới)
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Khóa chính, mã dòng nhật ký
  user_id        INT UNSIGNED NULL,                          -- Khóa ngoại: Người thao tác (NULL nếu do hệ thống chạy tự động)
  asset_id       INT UNSIGNED NULL,                          -- Khóa ngoại: Ấn phẩm chịu tác động
  version_id     INT UNSIGNED NULL,                          -- Khóa ngoại: Phiên bản chịu tác động (NULL nếu hành động ở mức asset)
  action         VARCHAR(50) NOT NULL,                       -- Hành động: upload, status_in_review, scored_ai, override...
  old_status     VARCHAR(20) NULL,                           -- Trạng thái trước khi thay đổi
  new_status     VARCHAR(20) NULL,                           -- Trạng thái sau khi thay đổi
  note           TEXT NULL,                                  -- Ghi chú chi tiết về sự kiện
  prompt_version VARCHAR(50) NULL,                           -- Phiên bản prompt (với sự kiện do AI chấm)
  model_version  VARCHAR(50) NULL,                           -- Phiên bản model (với sự kiện do AI chấm)
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Thời điểm sự kiện diễn ra
  CONSTRAINT fk_log_user    FOREIGN KEY (user_id)    REFERENCES users(id)          ON DELETE SET NULL,
  CONSTRAINT fk_log_asset   FOREIGN KEY (asset_id)   REFERENCES assets(id)         ON DELETE SET NULL,
  CONSTRAINT fk_log_version FOREIGN KEY (version_id) REFERENCES asset_versions(id) ON DELETE SET NULL,
  INDEX idx_log_asset (asset_id),
  INDEX idx_log_version (version_id),
  INDEX idx_log_time (created_at)
) ENGINE=InnoDB;

-- =====================================================================
-- PHẦN 2: CÁC QUY TẮC RÀNG BUỘC NGHIỆP VỤ (TRIGGERS & FUNCTION)
-- =====================================================================

DELIMITER $$

-- HÀM TIỆN ÍCH 1: Lấy nhanh vai trò (role) của người dùng qua ID
CREATE FUNCTION fn_role(p_user INT UNSIGNED) RETURNS VARCHAR(10)
READS SQL DATA
BEGIN
  DECLARE r VARCHAR(10);
  SELECT role INTO r FROM users WHERE id = p_user;
  RETURN r;
END$$

-- HÀM TIỆN ÍCH 2: Vai trò của người dùng TRONG MỘT CHIẾN DỊCH
-- Trả về 'admin' (Admin có quyền ở mọi chiến dịch), 'maker', 'checker' hoặc NULL (không thuộc chiến dịch)
CREATE FUNCTION fn_campaign_role(p_campaign INT UNSIGNED, p_user INT UNSIGNED) RETURNS VARCHAR(10)
READS SQL DATA
BEGIN
  DECLARE r VARCHAR(10);
  IF fn_role(p_user) = 'admin' THEN
    RETURN 'admin';
  END IF;
  SELECT role INTO r FROM campaign_members WHERE campaign_id = p_campaign AND user_id = p_user LIMIT 1;
  RETURN r;
END$$

-- HÀM TIỆN ÍCH 3: Phiên bản có tiêu chí nào đang FAIL không?
-- Mỗi tiêu chí chỉ tính dòng kết quả MỚI NHẤT (dòng ghi đè của Checker thay thế dòng cũ của AI)
CREATE FUNCTION fn_version_has_fail(p_version INT UNSIGNED) RETURNS TINYINT(1)
READS SQL DATA
BEGIN
  RETURN EXISTS (
    SELECT 1 FROM check_results cr
    WHERE cr.version_id = p_version
      AND cr.verdict = 'fail'
      AND cr.id = (SELECT MAX(c2.id) FROM check_results c2
                   WHERE c2.version_id = cr.version_id AND c2.criterion = cr.criterion)
  );
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R1: Chỉ Admin mới có quyền tạo Chiến dịch mới
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_campaigns_bi BEFORE INSERT ON campaigns FOR EACH ROW
BEGIN
  IF fn_role(NEW.created_by) <> 'admin' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R1: Chỉ Admin mới được tạo Campaign';
  END IF;
  -- Không chỉ định người phụ trách thì mặc định là người tạo
  SET NEW.owner_id = COALESCE(NEW.owner_id, NEW.created_by);
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R17: Phân quyền theo từng chiến dịch (thành viên phải đúng vai trò tài khoản)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_members_bi BEFORE INSERT ON campaign_members FOR EACH ROW
BEGIN
  IF fn_role(NEW.user_id) IS NULL OR fn_role(NEW.user_id) <> NEW.role THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R17: Vai trò trong chiến dịch phải trùng với vai trò tài khoản (maker/checker)';
  END IF;
END$$

CREATE TRIGGER trg_members_bu BEFORE UPDATE ON campaign_members FOR EACH ROW
BEGIN
  IF NEW.campaign_id <> OLD.campaign_id OR NEW.user_id <> OLD.user_id OR NEW.role <> OLD.role THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R17: Không sửa thành viên chiến dịch, hãy xóa và thêm lại';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R2, R3, R17:
-- - Chỉ Maker hoặc Admin mới được quyền tạo Asset
-- - Asset chỉ được tạo trong Campaign đang mở (active)
-- - Maker phải là thành viên (maker) của chính Campaign đó
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

  IF COALESCE(fn_campaign_role(NEW.campaign_id, NEW.created_by), '') NOT IN ('maker','admin') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R17: Bạn không phải Maker của chiến dịch này nên không tạo được Asset';
  END IF;

  SET NEW.current_version_id = NULL; -- phiên bản hiện hành do trigger của asset_versions tự cập nhật
END$$

-- Asset không được đổi chiến dịch hoặc người tạo; phiên bản hiện hành phải thuộc chính asset đó
CREATE TRIGGER trg_assets_bu BEFORE UPDATE ON assets FOR EACH ROW
BEGIN
  IF NEW.campaign_id <> OLD.campaign_id OR NEW.created_by <> OLD.created_by THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R6: Không được đổi chiến dịch hoặc người tạo của Asset';
  END IF;
  IF NOT (NEW.current_version_id <=> OLD.current_version_id) AND NEW.current_version_id IS NOT NULL THEN
    IF NOT EXISTS (SELECT 1 FROM asset_versions WHERE id = NEW.current_version_id AND asset_id = NEW.id) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R6: Phiên bản hiện hành phải thuộc chính Asset này';
    END IF;
  END IF;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R10: Asset đã có phiên bản vượt qua bước draft là dữ liệu lưu trữ, cấm xóa
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_assets_bd BEFORE DELETE ON assets FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM asset_versions WHERE asset_id = OLD.id AND status <> 'draft') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R10: Asset đã có phiên bản được chấm/duyệt/phát hành là dữ liệu lưu trữ, không được xóa';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R2, R3, R4, R5, R17: Kiểm soát tải tệp và tự động tăng số thứ tự phiên bản
-- - Chỉ Maker/Admin tải lên; chỉ NGƯỜI TẠO ASSET (hoặc Admin) được thêm phiên bản mới
-- - Maker phải thuộc chiến dịch; chiến dịch còn mở và còn hạn
-- - Chỉ tải phiên bản mới khi chưa có phiên bản nào, hoặc phiên bản mới nhất đang draft/rejected
-- - version_no tự động tăng n+1; phiên bản mới luôn bắt đầu ở 'draft'
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_versions_bi BEFORE INSERT ON asset_versions FOR EACH ROW
BEGIN
  DECLARE v_creator  INT UNSIGNED;
  DECLARE v_campaign INT UNSIGNED;
  DECLARE v_cstatus  VARCHAR(10);
  DECLARE v_deadline DATETIME;
  DECLARE v_last     VARCHAR(12);

  IF fn_role(NEW.uploaded_by) NOT IN ('maker','admin') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R2: Chỉ Maker hoặc Admin mới được phép tải tệp lên';
  END IF;

  SELECT a.created_by, a.campaign_id, c.status, c.deadline
  INTO v_creator, v_campaign, v_cstatus, v_deadline
  FROM assets a JOIN campaigns c ON c.id = a.campaign_id WHERE a.id = NEW.asset_id;

  IF v_creator <> NEW.uploaded_by AND fn_role(NEW.uploaded_by) <> 'admin' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R4: Chỉ người tạo Asset (hoặc Admin) mới được thêm phiên bản mới';
  END IF;
  IF COALESCE(fn_campaign_role(v_campaign, NEW.uploaded_by), '') NOT IN ('maker','admin') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R17: Bạn không phải Maker của chiến dịch này';
  END IF;

  IF v_cstatus <> 'active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R4: Chiến dịch đã đóng, không thể tải thêm tệp';
  END IF;
  IF v_deadline IS NOT NULL AND v_deadline < NOW() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R4: Đã quá hạn chót (deadline) của chiến dịch';
  END IF;

  SELECT status INTO v_last FROM asset_versions WHERE asset_id = NEW.asset_id ORDER BY version_no DESC LIMIT 1;
  IF v_last IS NOT NULL AND v_last NOT IN ('draft','rejected') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R4: Chỉ được tải phiên bản mới khi phiên bản mới nhất đang draft hoặc rejected';
  END IF;

  -- R5: Tự động tính số thứ tự phiên bản kế tiếp (version_no = max + 1) và khởi tạo sạch thông tin duyệt
  SET NEW.version_no = COALESCE(
    (SELECT MAX(version_no) FROM asset_versions WHERE asset_id = NEW.asset_id), 0) + 1;
  SET NEW.status = 'draft',
      NEW.reviewed_by = NULL, NEW.reviewed_at = NULL, NEW.review_note = NULL,
      NEW.released_by = NULL, NEW.released_at = NULL;
END$$

-- Cập nhật phiên bản hiện hành của asset và ghi nhật ký khi có phiên bản mới
CREATE TRIGGER trg_versions_ai AFTER INSERT ON asset_versions FOR EACH ROW
BEGIN
  UPDATE assets SET current_version_id = NEW.id WHERE id = NEW.asset_id;
  INSERT INTO audit_logs (user_id, asset_id, version_id, action, new_status, note)
  VALUES (NEW.uploaded_by, NEW.asset_id, NEW.id, 'upload', 'draft', CONCAT('Phiên bản số ', NEW.version_no));
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R5, R6, R7, R8, R9, R15, R16, R17: Luồng trạng thái của PHIÊN BẢN
-- - R5:  Không được sửa file/số phiên bản/người upload (không ghi đè tệp cũ)
-- - R6:  Luồng: draft -> scoring -> in_review -> approved/rejected; approved -> released
--        (scoring -> draft cho phép khi AI lỗi cần chấm lại; rejected và released là điểm cuối)
-- - R7:  Muốn vào 'in_review' thì phiên bản phải được AI chấm đủ 3 tiêu chí
-- - R8:  Phê duyệt/từ chối phải do Checker hoặc Admin, và thuộc chiến dịch (R17)
-- - R9:  Maker-Checker: người upload phiên bản không được duyệt phiên bản đó
-- - R15: Chỉ Admin được release, và chỉ từ 'approved'; đã released thì khóa hoàn toàn
-- - R16: Reject bắt buộc có lý do; Approve khi còn tiêu chí fail cũng bắt buộc có lý do
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_versions_bu BEFORE UPDATE ON asset_versions FOR EACH ROW
BEGIN
  DECLARE v_campaign INT UNSIGNED;

  IF OLD.status = 'released' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R15: Phiên bản đã phát hành (released) bị khóa, không được sửa';
  END IF;

  IF NEW.asset_id <> OLD.asset_id OR NEW.version_no <> OLD.version_no
     OR NOT (NEW.file_url <=> OLD.file_url) OR NEW.uploaded_by <> OLD.uploaded_by THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R5: Không được sửa tệp, số phiên bản hoặc người upload. Hãy tạo phiên bản mới (n+1)';
  END IF;

  IF NEW.status = OLD.status THEN
    -- Thông tin duyệt/phát hành chỉ được thay đổi cùng lúc với việc đổi trạng thái
    IF NOT (NEW.reviewed_by <=> OLD.reviewed_by AND NEW.reviewed_at <=> OLD.reviewed_at
            AND NEW.review_note <=> OLD.review_note
            AND NEW.released_by <=> OLD.released_by AND NEW.released_at <=> OLD.released_at) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R6: Thông tin duyệt/phát hành chỉ được ghi khi đổi trạng thái';
    END IF;
  ELSE
    -- R6: kiểm tra luồng chuyển trạng thái hợp lệ
    IF NOT (   (OLD.status = 'draft'     AND NEW.status = 'scoring')
            OR (OLD.status = 'scoring'   AND NEW.status IN ('in_review','draft'))
            OR (OLD.status = 'in_review' AND NEW.status IN ('approved','rejected'))
            OR (OLD.status = 'approved'  AND NEW.status = 'released')) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R6: Chuyển trạng thái không hợp lệ theo quy trình';
    END IF;

    -- R7: phải có kết quả AI đủ 3 tiêu chí trước khi vào in_review
    IF NEW.status = 'in_review' THEN
      IF (SELECT COUNT(DISTINCT criterion) FROM check_results
          WHERE version_id = NEW.id AND checker_type = 'ai') < 3 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R7: Phiên bản chưa được AI chấm đủ 3 tiêu chí';
      END IF;
    END IF;

    -- R8, R9, R16, R17: điều kiện khi duyệt hoặc từ chối
    IF NEW.status IN ('approved','rejected') THEN
      SELECT campaign_id INTO v_campaign FROM assets WHERE id = NEW.asset_id;
      IF NEW.reviewed_by IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R8: Phải có thông tin người duyệt (reviewed_by)';
      END IF;
      IF fn_role(NEW.reviewed_by) NOT IN ('checker','admin') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R8: Chỉ Checker hoặc Admin mới có quyền duyệt phiên bản';
      END IF;
      IF COALESCE(fn_campaign_role(v_campaign, NEW.reviewed_by), '') NOT IN ('checker','admin') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R17: Người duyệt không phải Checker của chiến dịch này';
      END IF;
      IF NEW.reviewed_by = NEW.uploaded_by THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R9: Nguyên tắc kiểm soát chéo: Không được tự duyệt phiên bản do chính mình upload';
      END IF;
      IF NEW.status = 'rejected' AND (NEW.review_note IS NULL OR TRIM(NEW.review_note) = '') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R16: Từ chối bắt buộc phải nhập lý do (review_note)';
      END IF;
      IF NEW.status = 'approved' AND fn_version_has_fail(NEW.id)
         AND (NEW.review_note IS NULL OR TRIM(NEW.review_note) = '') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R16: Phiên bản còn tiêu chí FAIL, duyệt bắt buộc phải nhập lý do (review_note)';
      END IF;
      SET NEW.reviewed_at = NOW();
    END IF;

    -- R15: phát hành chỉ dành cho Admin
    IF NEW.status = 'released' THEN
      IF NEW.released_by IS NULL OR fn_role(NEW.released_by) <> 'admin' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R15: Chỉ Admin mới được phát hành (release)';
      END IF;
      SET NEW.released_at = NOW();
    END IF;
  END IF;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R14: Tự động ghi nhật ký (Audit Log) khi trạng thái phiên bản thay đổi
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_versions_au AFTER UPDATE ON asset_versions FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN
    INSERT INTO audit_logs (user_id, asset_id, version_id, action, old_status, new_status, note)
    VALUES (COALESCE(@app_user_id, IF(NEW.status = 'released', NEW.released_by, NEW.reviewed_by)),
            NEW.asset_id, NEW.id,
            CASE WHEN NEW.status = 'approved' AND fn_version_has_fail(NEW.id) THEN 'status_approved_with_fail'
                 ELSE CONCAT('status_', NEW.status) END,
            OLD.status, NEW.status, NEW.review_note);
  END IF;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R10: Chỉ được xóa phiên bản còn ở trạng thái draft (giữ lịch sử duyệt)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_versions_bd BEFORE DELETE ON asset_versions FOR EACH ROW
BEGIN
  IF OLD.status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R10: Chỉ được xóa phiên bản đang ở trạng thái draft';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R11, R12, R13, R17: Ràng buộc khi lưu kết quả chấm điểm
-- - R13: Chỉ chấm điểm cho phiên bản tệp mới nhất
-- - R11: AI chỉ chấm khi phiên bản ở trạng thái scoring; checker_id để trống (NULL);
--        bắt buộc ghi prompt_version và model_version
-- - R12: Người chỉ chấm khi phiên bản in_review; không tự chấm bài do mình upload;
--        ghi đè phải có điểm AI trước đó CỦA CÙNG TIÊU CHÍ
-- - R17: Người chấm phải là Checker của chiến dịch (Admin ngoại lệ)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_results_bi BEFORE INSERT ON check_results FOR EACH ROW
BEGIN
  DECLARE v_asset    INT UNSIGNED;
  DECLARE v_vno      INT UNSIGNED;
  DECLARE v_status   VARCHAR(12);
  DECLARE v_uploader INT UNSIGNED;
  DECLARE v_campaign INT UNSIGNED;

  SELECT v.asset_id, v.version_no, v.status, v.uploaded_by, a.campaign_id
  INTO v_asset, v_vno, v_status, v_uploader, v_campaign
  FROM asset_versions v JOIN assets a ON a.id = v.asset_id WHERE v.id = NEW.version_id;

  -- Bắt buộc chỉ chấm cho phiên bản mới nhất
  IF v_vno <> (SELECT MAX(version_no) FROM asset_versions WHERE asset_id = v_asset) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R13: Chỉ được chấm điểm cho phiên bản tệp mới nhất';
  END IF;

  IF NEW.checker_type = 'ai' THEN
    IF NEW.checker_id IS NOT NULL OR NEW.is_override THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R11: Kết quả do AI chấm: checker_id phải là NULL và không được đánh dấu ghi đè';
    END IF;
    IF v_status <> 'scoring' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R11: AI chỉ được thực hiện chấm khi phiên bản ở trạng thái scoring';
    END IF;
    IF NEW.prompt_version IS NULL OR NEW.model_version IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R11: Kết quả AI bắt buộc ghi rõ prompt_version và model_version';
    END IF;
  ELSE
    IF NEW.checker_id IS NULL OR fn_role(NEW.checker_id) NOT IN ('checker','admin') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Chỉ Checker hoặc Admin mới có quyền chấm điểm thủ công';
    END IF;
    IF NEW.checker_id = v_uploader THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Người kiểm duyệt không được tự chấm điểm phiên bản do mình upload';
    END IF;
    IF COALESCE(fn_campaign_role(v_campaign, NEW.checker_id), '') NOT IN ('checker','admin') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R17: Người chấm không phải Checker của chiến dịch này';
    END IF;
    IF v_status <> 'in_review' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Người thẩm định chỉ được chấm khi phiên bản đang ở trạng thái in_review';
    END IF;
    IF NEW.is_override AND NOT EXISTS (
         SELECT 1 FROM check_results
         WHERE version_id = NEW.version_id AND criterion = NEW.criterion AND checker_type = 'ai') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Ghi đè (override) yêu cầu phải có kết quả chấm từ AI trước đó cho cùng tiêu chí';
    END IF;
  END IF;
END$$

-- Tự động ghi nhật ký hệ thống sau khi lưu kết quả chấm điểm
CREATE TRIGGER trg_results_ai AFTER INSERT ON check_results FOR EACH ROW
BEGIN
  INSERT INTO audit_logs (user_id, asset_id, version_id, action, note, prompt_version, model_version)
  SELECT NEW.checker_id, v.asset_id, NEW.version_id,
         IF(NEW.is_override, 'override', CONCAT('scored_', NEW.checker_type)),
         CONCAT('Tiêu chí: ', NEW.criterion, ' | Điểm số: ', NEW.score, ' | Kết luận: ', NEW.verdict),
         NEW.prompt_version, NEW.model_version
  FROM asset_versions v WHERE v.id = NEW.version_id;
END$$

-- ---------------------------------------------------------------------
-- QUY TẮC R13: Không cho phép sửa hoặc xóa kết quả đã chấm (phải thêm dòng mới nếu muốn ghi đè)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_results_bu BEFORE UPDATE ON check_results FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R13: Kết quả chấm điểm không được phép sửa, hãy thêm dòng mới với cờ ghi đè (is_override)';
END$$

CREATE TRIGGER trg_results_bd BEFORE DELETE ON check_results FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R13: Kết quả chấm điểm không được phép xóa';
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
