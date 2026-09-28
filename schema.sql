-- =====================================================================
-- DATABASE SCHEMA + RULES - DU AN 1 (Maker-Checker + AI cham diem)
-- MySQL 8.0.16+ (LocalWP dung MySQL 8). Chay lai file nay = reset sach.
-- =====================================================================
CREATE DATABASE IF NOT EXISTS mas_project
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mas_project;

-- Xoa bang cu (neu co) de chay lai duoc nhieu lan
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs, check_results, asset_versions, assets, campaigns, users;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- PHAN 1: BANG DU LIEU
-- =====================================================================

-- 1. USERS: nguoi dung + vai tro
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('maker','checker','admin') NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. CAMPAIGNS: chien dich (deadline + tai lieu guideline)
CREATE TABLE campaigns (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(150) NOT NULL,
  description        TEXT NULL,
  deadline           DATETIME NULL,
  guideline_file_url VARCHAR(500) NULL,
  status             ENUM('active','closed') NOT NULL DEFAULT 'active',
  created_by         INT UNSIGNED NOT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_campaign_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 3. ASSETS: tai san quang cao
--    created_by = Maker tao ra; reviewed_by = Checker/Admin duyet hoac tu choi
CREATE TABLE assets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT UNSIGNED NOT NULL,
  title       VARCHAR(200) NOT NULL,
  status      ENUM('draft','scoring','in_review','approved','rejected')
              NOT NULL DEFAULT 'draft',
  created_by  INT UNSIGNED NOT NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_asset_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
  CONSTRAINT fk_asset_creator  FOREIGN KEY (created_by)  REFERENCES users(id),
  CONSTRAINT fk_asset_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id),
  INDEX idx_asset_status (status)
) ENGINE=InnoDB;

-- 4. ASSET_VERSIONS: moi lan upload lai = 1 version moi (n+1, tu dong tinh)
CREATE TABLE asset_versions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_id    INT UNSIGNED NOT NULL,
  version_no  INT UNSIGNED NOT NULL DEFAULT 0,   -- trigger tu dien n+1
  file_url    VARCHAR(500) NOT NULL,
  file_type   VARCHAR(20)  NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_version_asset FOREIGN KEY (asset_id)    REFERENCES assets(id) ON DELETE CASCADE,
  CONSTRAINT fk_version_user  FOREIGN KEY (uploaded_by) REFERENCES users(id),
  UNIQUE KEY uq_asset_version (asset_id, version_no)
) ENGINE=InnoDB;

-- 5. CHECK_RESULTS: ket qua cham diem (AI hoac nguoi)
--    verdict tu tinh: 0-30 fail, 31-70 needs_look, 71-100 pass
--    is_override = Checker ghi de ket qua AI (them dong moi, khong sua dong cu)
CREATE TABLE check_results (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version_id   INT UNSIGNED NOT NULL,
  checker_type ENUM('ai','human') NOT NULL DEFAULT 'ai',
  checker_id   INT UNSIGNED NULL,               -- NULL neu la AI
  score        TINYINT UNSIGNED NOT NULL,
  verdict      VARCHAR(12) GENERATED ALWAYS AS (
                 CASE WHEN score <= 30 THEN 'fail'
                      WHEN score <= 70 THEN 'needs_look'
                      ELSE 'pass' END
               ) STORED,
  reason       TEXT NULL,
  source_quote TEXT NULL,
  raw_json     JSON NULL,
  is_override  BOOLEAN NOT NULL DEFAULT FALSE,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_result_version FOREIGN KEY (version_id) REFERENCES asset_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_result_checker FOREIGN KEY (checker_id) REFERENCES users(id),
  CONSTRAINT chk_score_range CHECK (score BETWEEN 0 AND 100)
) ENGINE=InnoDB;

-- 6. AUDIT_LOGS: nhat ky (chi them, khong sua/xoa duoc)
CREATE TABLE audit_logs (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,                 -- NULL neu he thong/job nen lam
  asset_id   INT UNSIGNED NULL,
  action     VARCHAR(50) NOT NULL,
  old_status VARCHAR(20) NULL,
  new_status VARCHAR(20) NULL,
  note       TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE SET NULL,
  CONSTRAINT fk_log_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
  INDEX idx_log_asset (asset_id),
  INDEX idx_log_time (created_at)
) ENGINE=InnoDB;

-- =====================================================================
-- PHAN 2: RULES (TRIGGER) - database tu bao ve, code nao goi vao cung bi chan
-- =====================================================================
-- Danh sach rule:
--  R1  Chi Admin tao Campaign
--  R2  Chi Maker/Admin tao Asset va upload Version
--  R3  Asset chi tao trong Campaign dang active, luon bat dau o 'draft'
--  R4  Upload chi khi Asset o 'draft' hoac 'rejected', va Campaign chua qua deadline
--  R5  version_no tu dong n+1; upload lai asset 'rejected' -> tu ve 'draft'
--  R6  Trang thai chi di dung luong:
--        draft -> scoring -> in_review -> approved | rejected ; rejected -> draft
--  R7  Muon vao 'in_review' thi phien ban moi nhat phai co ket qua cham diem
--  R8  Duyet/tu choi phai co nguoi (reviewed_by) la Checker/Admin
--  R9  KHONG tu duyet Asset cua chinh minh (reviewed_by <> created_by)
--  R10 Asset da 'approved' la cuoi cung: khong doi trang thai, khong xoa
--  R11 Ket qua AI: checker_id phai NULL, chi cham khi Asset 'scoring'
--  R12 Ket qua nguoi: chi Checker/Admin, khong tu cham asset minh tao,
--      chi cham khi Asset 'in_review'; Override phai co ket qua AI truoc do
--  R13 Chi cham phien ban MOI NHAT; ket qua da luu khong duoc sua
--  R14 Audit_logs khong sua/xoa duoc; doi trang thai, upload, cham diem tu ghi log
--
-- Ghi chu: ung dung nen chay  SET @app_user_id = <id nguoi dang nhap>;
-- truoc khi doi trang thai de audit_logs ghi dung nguoi lam.

DROP FUNCTION IF EXISTS fn_role;
DELIMITER $$
CREATE FUNCTION fn_role(p_user INT UNSIGNED) RETURNS VARCHAR(10)
READS SQL DATA
BEGIN
  DECLARE r VARCHAR(10);
  SELECT role INTO r FROM users WHERE id = p_user;
  RETURN r;
END$$

-- ---------- CAMPAIGNS ----------
CREATE TRIGGER trg_campaigns_bi BEFORE INSERT ON campaigns FOR EACH ROW
BEGIN
  IF fn_role(NEW.created_by) <> 'admin' THEN                              -- R1
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R1: Chi Admin moi duoc tao Campaign';
  END IF;
END$$

-- ---------- ASSETS ----------
CREATE TRIGGER trg_assets_bi BEFORE INSERT ON assets FOR EACH ROW
BEGIN
  DECLARE v_cstatus VARCHAR(10);
  IF fn_role(NEW.created_by) NOT IN ('maker','admin') THEN                -- R2
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R2: Chi Maker hoac Admin moi duoc tao Asset';
  END IF;
  SELECT status INTO v_cstatus FROM campaigns WHERE id = NEW.campaign_id;
  IF v_cstatus <> 'active' THEN                                           -- R3
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R3: Campaign da dong, khong tao them Asset';
  END IF;
  SET NEW.status = 'draft', NEW.reviewed_by = NULL, NEW.reviewed_at = NULL;
END$$

CREATE TRIGGER trg_assets_bu BEFORE UPDATE ON assets FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN
    -- R6 + R10: chi cac buoc chuyen hop le (approved khong di dau nua)
    IF NOT (   (OLD.status = 'draft'     AND NEW.status = 'scoring')
            OR (OLD.status = 'scoring'   AND NEW.status = 'in_review')
            OR (OLD.status = 'in_review' AND NEW.status IN ('approved','rejected'))
            OR (OLD.status = 'rejected'  AND NEW.status = 'draft')) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R6: Chuyen trang thai khong hop le';
    END IF;

    -- R7: vao in_review can co ket qua cham diem cho phien ban moi nhat
    IF NEW.status = 'in_review' THEN
      IF NOT EXISTS (
        SELECT 1 FROM check_results cr
        JOIN asset_versions v ON v.id = cr.version_id
        WHERE v.asset_id = NEW.id
          AND v.version_no = (SELECT MAX(version_no) FROM asset_versions WHERE asset_id = NEW.id)
      ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R7: Phien ban moi nhat chua co ket qua cham diem';
      END IF;
    END IF;

    -- R8 + R9: duyet / tu choi
    IF NEW.status IN ('approved','rejected') THEN
      IF NEW.reviewed_by IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R8: Phai co nguoi duyet (reviewed_by)';
      END IF;
      IF fn_role(NEW.reviewed_by) NOT IN ('checker','admin') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R8: Chi Checker hoac Admin moi duoc duyet';
      END IF;
      IF NEW.reviewed_by = NEW.created_by THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R9: Khong duoc tu duyet Asset cua chinh minh';
      END IF;
      SET NEW.reviewed_at = NOW();
    END IF;

    IF NEW.status = 'draft' THEN
      SET NEW.reviewed_by = NULL, NEW.reviewed_at = NULL;
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_assets_au AFTER UPDATE ON assets FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN                                        -- R14
    INSERT INTO audit_logs (user_id, asset_id, action, old_status, new_status)
    VALUES (COALESCE(@app_user_id, NEW.reviewed_by), NEW.id,
            CONCAT('status_', NEW.status), OLD.status, NEW.status);
  END IF;
END$$

CREATE TRIGGER trg_assets_bd BEFORE DELETE ON assets FOR EACH ROW
BEGIN
  IF OLD.status = 'approved' THEN                                         -- R10
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R10: Asset da approved khong duoc xoa';
  END IF;
END$$

-- ---------- ASSET_VERSIONS ----------
CREATE TRIGGER trg_versions_bi BEFORE INSERT ON asset_versions FOR EACH ROW
BEGIN
  DECLARE v_status   VARCHAR(12);
  DECLARE v_cstatus  VARCHAR(10);
  DECLARE v_deadline DATETIME;

  IF fn_role(NEW.uploaded_by) NOT IN ('maker','admin') THEN               -- R2
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R2: Chi Maker hoac Admin moi duoc upload';
  END IF;

  SELECT a.status, c.status, c.deadline INTO v_status, v_cstatus, v_deadline
  FROM assets a JOIN campaigns c ON c.id = a.campaign_id WHERE a.id = NEW.asset_id;

  IF v_status NOT IN ('draft','rejected') THEN                            -- R4
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R4: Chi upload khi Asset o trang thai draft hoac rejected';
  END IF;
  IF v_cstatus <> 'active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R4: Campaign da dong';
  END IF;
  IF v_deadline IS NOT NULL AND v_deadline < NOW() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R4: Da qua deadline cua Campaign';
  END IF;

  SET NEW.version_no = COALESCE(                                          -- R5
    (SELECT MAX(version_no) FROM asset_versions WHERE asset_id = NEW.asset_id), 0) + 1;
END$$

CREATE TRIGGER trg_versions_ai AFTER INSERT ON asset_versions FOR EACH ROW
BEGIN
  UPDATE assets SET status = 'draft' WHERE id = NEW.asset_id AND status = 'rejected';  -- R5
  INSERT INTO audit_logs (user_id, asset_id, action, note)                              -- R14
  VALUES (NEW.uploaded_by, NEW.asset_id, 'upload', CONCAT('version ', NEW.version_no));
END$$

-- ---------- CHECK_RESULTS ----------
CREATE TRIGGER trg_results_bi BEFORE INSERT ON check_results FOR EACH ROW
BEGIN
  DECLARE v_asset   INT UNSIGNED;
  DECLARE v_vno     INT UNSIGNED;
  DECLARE v_status  VARCHAR(12);
  DECLARE v_creator INT UNSIGNED;

  SELECT v.asset_id, v.version_no, a.status, a.created_by
  INTO v_asset, v_vno, v_status, v_creator
  FROM asset_versions v JOIN assets a ON a.id = v.asset_id WHERE v.id = NEW.version_id;

  IF v_vno <> (SELECT MAX(version_no) FROM asset_versions WHERE asset_id = v_asset) THEN  -- R13
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R13: Chi duoc cham phien ban moi nhat';
  END IF;

  IF NEW.checker_type = 'ai' THEN                                         -- R11
    IF NEW.checker_id IS NOT NULL OR NEW.is_override THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R11: Ket qua AI: checker_id phai NULL va khong duoc override';
    END IF;
    IF v_status <> 'scoring' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R11: AI chi cham khi Asset o trang thai scoring';
    END IF;
  ELSE                                                                    -- R12
    IF NEW.checker_id IS NULL OR fn_role(NEW.checker_id) NOT IN ('checker','admin') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Chi Checker hoac Admin moi duoc cham';
    END IF;
    IF NEW.checker_id = v_creator THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Khong duoc tu cham Asset cua chinh minh';
    END IF;
    IF v_status <> 'in_review' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Nguoi chi cham khi Asset o trang thai in_review';
    END IF;
    IF NEW.is_override AND NOT EXISTS (
         SELECT 1 FROM check_results WHERE version_id = NEW.version_id AND checker_type = 'ai') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R12: Override can co ket qua AI truoc do';
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_results_ai AFTER INSERT ON check_results FOR EACH ROW
BEGIN
  INSERT INTO audit_logs (user_id, asset_id, action, note)                -- R14
  SELECT NEW.checker_id, v.asset_id,
         IF(NEW.is_override, 'override', CONCAT('scored_', NEW.checker_type)),
         CONCAT('score=', NEW.score, ' verdict=', NEW.verdict)
  FROM asset_versions v WHERE v.id = NEW.version_id;
END$$

CREATE TRIGGER trg_results_bu BEFORE UPDATE ON check_results FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R13: Ket qua cham diem khong duoc sua, hay them dong moi (override)';
END$$

-- ---------- AUDIT_LOGS: chi them, khong sua/xoa ----------
CREATE TRIGGER trg_logs_bu BEFORE UPDATE ON audit_logs FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R14: Audit log khong duoc sua';
END$$

CREATE TRIGGER trg_logs_bd BEFORE DELETE ON audit_logs FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'R14: Audit log khong duoc xoa';
END$$
DELIMITER ;
