# 2.0.0 Migration

## Previous Global Sections Data

### Locations

```json
[
    {
        "key": "LL9dL4s9wSxEpW_mBbqIe",
        "options": [
            {
                "type": "priority",
                "value": "10"
            },
            {
                "type": "location",
                "value": "nectar_hook_global_section_footer"
            }
        ]
    },
    {
        "key": "GuRRETBZYzATz3fkijq4E",
        "options": [
            {
                "type": "priority",
                "value": 9
            },
            {
                "type": "location",
                "value": "nectar_hook_global_section_after_header_navigation"
            }
        ]
    }
]
```

### Conditions

```json
[
    {
        "key": "t-u3u2JYLifbdYbfeyCb2",
        "options": [
            {
                "type": "include",
                "value": "include"
            },
            {
                "type": "condition",
                "value": "is_search"
            }
        ]
    },
    {
        "key": "2wZ7RlSbPsD4e56MuJAma",
        "options": [
            {
                "type": "include",
                "value": "exclude"
            },
            {
                "type": "condition",
                "value": "is_search"
            }
        ]
    }
]
```

### Updated

```ts
{
  operator: 'and' | 'or';
  conditions: {
    key: string;
    include: boolean;
    condition: string;
  }[];
  locations: {
    key: string;
    priority: number;
    location: string;
  }[]
}
```
