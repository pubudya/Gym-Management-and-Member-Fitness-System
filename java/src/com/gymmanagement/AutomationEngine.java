package com.gymmanagement;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.Statement;
import java.time.LocalDate;
import java.util.Properties;

/**
 * Java automation helper for Volt Gym.
 * PHP remains the web/SMS application. This CLI job can be scheduled
 * to expire memberships, flag low stock and print an operations snapshot.
 *
 * Compile: javac -cp "lib/mysql-connector-j-8.4.0.jar" -d out src/com/gymmanagement/AutomationEngine.java
 * Run:     java -cp "out;lib/mysql-connector-j-8.4.0.jar" com.gymmanagement.AutomationEngine
 */
public class AutomationEngine {
    public static void main(String[] args) throws Exception {
        String host = env("GYM_DB_HOST", "127.0.0.1");
        String db = env("GYM_DB_NAME", "gym_management");
        String user = env("GYM_DB_USER", "root");
        String pass = env("GYM_DB_PASS", "");

        String url = "jdbc:mysql://" + host + ":3306/" + db + "?useSSL=false&allowPublicKeyRetrieval=true&serverTimezone=Asia/Colombo";
        Properties props = new Properties();
        props.setProperty("user", user);
        props.setProperty("password", pass);

        try (Connection conn = DriverManager.getConnection(url, props)) {
            int expired = expireMemberships(conn);
            int lowStock = flagLowStock(conn);
            int occupancy = scalar(conn, "SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND check_out IS NULL");
            int pendingPay = scalar(conn, "SELECT COUNT(*) FROM memberships WHERE balance > 0 AND status IN ('ACTIVE','PENDING')");

            System.out.println("Volt Gym Java automation");
            System.out.println("Date: " + LocalDate.now());
            System.out.println("Expired memberships updated: " + expired);
            System.out.println("Low-stock items flagged: " + lowStock);
            System.out.println("Current occupancy: " + occupancy);
            System.out.println("Memberships with outstanding balance: " + pendingPay);
        }
    }

    private static int expireMemberships(Connection conn) throws Exception {
        try (PreparedStatement ps = conn.prepareStatement(
                "UPDATE memberships SET status='EXPIRED' WHERE status='ACTIVE' AND end_date < CURDATE()")) {
            return ps.executeUpdate();
        }
    }

    private static int flagLowStock(Connection conn) throws Exception {
        try (PreparedStatement ps = conn.prepareStatement(
                "UPDATE inventory SET status = CASE WHEN quantity = 0 THEN 'OUT_OF_STOCK' WHEN quantity <= minimum_stock THEN 'LOW_STOCK' ELSE 'AVAILABLE' END")) {
            return ps.executeUpdate();
        }
    }

    private static int scalar(Connection conn, String sql) throws Exception {
        try (Statement st = conn.createStatement(); ResultSet rs = st.executeQuery(sql)) {
            return rs.next() ? rs.getInt(1) : 0;
        }
    }

    private static String env(String key, String fallback) {
        String v = System.getenv(key);
        return (v == null || v.isBlank()) ? fallback : v;
    }
}
