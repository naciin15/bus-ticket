package backend.Java;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class BookingSystem {
    private static final String DB_URL = "jdbc:mysql://localhost:3306/bus_booking";
    private static final String USER = "bus_user";
    private static final String PASS = "localpass123";
    
    public static void main(String[] args) {
        BookingSystem system = new BookingSystem();
        List<Bus> buses = system.getAvailableBuses("paris", "frankfurt", "2023-11-20");
        System.out.println("Found buses: " + buses.size());
        system.close();
    }
    
    public List<Bus> getAvailableBuses(String from, String to, String date) {
        List<Bus> buses = new ArrayList<>();
        
        try (Connection conn = DriverManager.getConnection(DB_URL, USER, PASS);
             PreparedStatement stmt = conn.prepareStatement(
                 "SELECT * FROM buses WHERE departure_city=? AND arrival_city=? AND date=?")) {
            
            stmt.setString(1, from);
            stmt.setString(2, to);
            stmt.setString(3, date);
            
            ResultSet rs = stmt.executeQuery();
            
            while (rs.next()) {
                Bus bus = new Bus();
                bus.setId(rs.getInt("id"));
                bus.setBusNumber(rs.getString("bus_number"));
                bus.setDepartureCity(rs.getString("departure_city"));
                bus.setArrivalCity(rs.getString("arrival_city"));
                buses.add(bus);
            }
        } catch (SQLException e) {
            e.printStackTrace();
        }
        return buses;
    }
    
    public void close() {}
}

class Bus {
    private int id;
    private String busNumber;
    private String departureCity;
    private String arrivalCity;
    
    // Getters and setters
    public int getId() { return id; }
    public void setId(int id) { this.id = id; }
    public String getBusNumber() { return busNumber; }
    public void setBusNumber(String busNumber) { this.busNumber = busNumber; }
    public String getDepartureCity() { return departureCity; }
    public void setDepartureCity(String departureCity) { this.departureCity = departureCity; }
    public String getArrivalCity() { return arrivalCity; }
    public void setArrivalCity(String arrivalCity) { this.arrivalCity = arrivalCity; }
    
    @Override
    public String toString() {
        return busNumber + ": " + departureCity + " → " + arrivalCity;
    }
}