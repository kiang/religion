# Taiwan Religious POI Data

This project archives Point of Interest (POI) data of religious locations in Taiwan. The data is sourced from the [Ministry of Interior's religion database](https://religion.moi.gov.tw/).

## Visualization

The data can be explored through an interactive map at https://kiang.github.io/religions/

## Data License

The POI data is licensed under CC-BY (Creative Commons Attribution).

## Code License

All scripts and code in this repository are licensed under MIT License.

## Data Source

- Source: https://religion.moi.gov.tw/
- Update frequency: Daily
- Format: GeoJSON
- Visualization: https://kiang.github.io/religions/

## Usage

```bash
php fetch.php # Fetches latest data from the Ministry of Interior and converts to GeoJSON
```

## Data Structure

The data is stored in GeoJSON format with the following properties:
- Geometry: Point
- Properties include religious site information from MOI database

Example:
```json
{
  "type": "Feature",
  "geometry": {
    "type": "Point",
    "coordinates": [longitude, latitude]
  },
  "properties": {
    // Temple/religious site properties
  }
}
```

