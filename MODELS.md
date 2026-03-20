# Eloquent Models & Data Flow

This document maps every Eloquent model, their relationships, and how data flows through the three main modules: **Shop**, **Blog**, and **HR**.

---

## Model Map

### Auth / Tenancy

#### `User`
- `BelongsToMany` → `Team` (pivot: `team_user`)
- Implements `FilamentUser`, `HasTenants`, `MustVerifyEmail`
- Uses `HasApiTokens` (Sanctum), `Notifiable`
- `getTenants()` returns all Teams — every user can access every team

#### `Team`
- `BelongsToMany` → `User`
- Implements `HasCurrentTenantLabel` — used as Filament's multi-tenancy boundary

---

### Shop Module (`App\Models\Shop`)

#### `Brand`
- `HasMany` → `Product`
- `MorphToMany` → `Address` (via `addressable` polymorphic pivot)
- `HasMedia` (Spatie MediaLibrary)

#### `ProductCategory`
- `BelongsTo` → `ProductCategory` (self-referential, `parent_id`)
- `HasMany` → `ProductCategory` (children, `parent_id`)
- `BelongsToMany` → `Product` (pivot: `product_category_product`)
- `HasMedia` (Spatie MediaLibrary)
- Casts: `is_visible` → boolean

#### `Product`
- `BelongsTo` → `Brand`
- `BelongsToMany` → `ProductCategory` (pivot: `product_category_product`)
- `MorphMany` → `Comment` (as `commentable`)
- `HasMedia` — collection: `product-images` (JPEG only, `thumb` conversion 40×40)
- Casts: `featured`, `is_visible`, `backorder`, `requires_shipping` → boolean; `published_at` → date

#### `Customer` 
- `HasMany` → `Order`
- `HasMany` → `Comment`
- `HasManyThrough` → `Payment` (through `Order`)
- `MorphToMany` → `Address` (via `addressable`)
- `SoftDeletes`

#### `Order`
- `BelongsTo` → `Customer`
- `HasMany` → `OrderItem`
- `HasMany` → `Payment`
- `MorphOne` → `OrderAddress` (as `addressable`)
- `SoftDeletes`
- Casts: `status` → `OrderStatus` enum; `currency` → `CurrencyCode` enum; `shipping_price`, `total_price` → decimal

#### `OrderItem`
- `BelongsTo` → `Order`
- `BelongsTo` → `Product`

#### `Payment`
- `BelongsTo` → `Order`
- Casts: `currency` → `CurrencyCode` enum

#### `OrderAddress`
- `MorphTo` → `addressable` (polymorphic; used by `Order`)
- Casts: `country` → `CountryCode` enum

#### `Address`
- `MorphedByMany` → `Customer` (via `addressable`)
- `MorphedByMany` → `Brand` (via `addressable`)
- Casts: `country` → `CountryCode` enum
- Note: this is the shared address book used by Customers and Brands. `OrderAddress` is a separate, order-specific snapshot.

---

### Blog Module (`App\Models\Blog`)

#### `Author`
- `HasMany` → `Post`

#### `PostCategory`
- `HasMany` → `Post`
- Casts: `is_visible` → boolean

#### `Post`
- `BelongsTo` → `Author`
- `BelongsTo` → `PostCategory`
- `MorphMany` → `Comment` (as `commentable`)
- `HasTags` (Spatie Tags)
- `HasMedia` — collection: `post-images` (JPEG only, `thumb` conversion 360×240, `og` conversion 1200×630)
- Casts: `is_visible` → boolean; `published_at` → date

#### `Comment` (`App\Models\Comment`)
- `BelongsTo` → `Customer`
- `MorphTo` → `commentable` (can be `Post` or `Product`)

---

### HR Module (`App\Models\HR`)

#### `Department`
- `BelongsTo` → `Department` (self-referential, `parent_id`)
- `HasMany` → `Department` (children)
- `HasMany` → `Employee`
- `HasMany` → `Project`

#### `Employee`
- `BelongsTo` → `Department`
- `HasMany` → `LeaveRequest` (as requester)
- `HasMany` → `LeaveRequest` as `approvedLeaveRequests` (`approver_id` FK)
- `HasMany` → `Task` as `assignedTasks` (`assigned_to` FK)
- `HasMany` → `Timesheet`
- `HasMany` → `Expense`
- `SoftDeletes`
- Casts: `date_of_birth` → date; `date_hired` → date

#### `LeaveRequest`
- `BelongsTo` → `Employee` (requester)
- `BelongsTo` → `Employee` as `approver` (`approver_id` FK)
- Casts: `type` → `LeaveType` enum; `status` → `LeaveStatus` enum; `start_date`, `end_date` → date; `days_requested` → decimal; `reviewed_at` → datetime

#### `Project`
- `BelongsTo` → `Department`
- `HasMany` → `Task`
- `HasMany` → `Timesheet`
- `HasMany` → `Expense`
- `SoftDeletes`
- Casts: `status` → `ProjectStatus` enum; `priority` → `TaskPriority` enum; `budget`, `spent` → decimal; `estimated_hours`, `actual_hours` → decimal; `start_date`, `end_date` → date; `plan` → array

#### `Task`
- `BelongsTo` → `Project`
- `BelongsTo` → `Employee` as `assignee` (`assigned_to` FK)
- `HasMany` → `Timesheet`
- Casts: `status` → `TaskStatus` enum; `priority` → `TaskPriority` enum; `estimated_hours`, `actual_hours` → decimal; `due_date` → date; `completed_at` → datetime; `labels` → array

#### `Timesheet`
- `BelongsTo` → `Employee`
- `BelongsTo` → `Task`
- `BelongsTo` → `Project`
- Casts: `date` → date; `hours`, `hourly_rate`, `total_cost` → decimal; `is_billable` → boolean

#### `Expense`
- `BelongsTo` → `Employee`
- `BelongsTo` → `Project`
- `BelongsTo` → `Employee` as `approvedBy` (`approved_by` FK)
- `HasMany` → `ExpenseLine`
- Casts: `status` → `ExpenseStatus` enum; `category` → `ExpenseCategory` enum; `total_amount` → decimal; `submitted_at`, `approved_at` → datetime

#### `ExpenseLine`
- `BelongsTo` → `Expense`

---

## Data Flow Diagrams

### Creating an Order

```
Customer
  └─ creates ──► Order
                   ├─ has many ──► OrderItem ──► Product (via product_id)
                   ├─ has many ──► Payment    (currency: CurrencyCode)
                   └─ morph one ► OrderAddress (country: CountryCode)
```

1. A `Customer` record must exist first.
2. An `Order` is created with `customer_id` + status/currency.
3. One or more `OrderItem` rows are created linking `order_id` → `product_id` with qty/price.
4. A `Payment` is recorded with `order_id` and currency.
5. An `OrderAddress` is created as a polymorphic record (`addressable_type = Order`, `addressable_id = order.id`) — this snapshots the delivery address at order time, independent of any shared `Address` on the Customer.

Key relationships accessed during this flow:
- `Order::items()` → `OrderItem` → `Product`
- `Order::payments()` → `Payment`
- `Order::address()` → `OrderAddress`
- `Customer::payments()` → all payments via `hasManyThrough(Payment, Order)`

---

### Publishing a Post

```
Author
  └─ writes ──► Post
                 ├─ belongs to ──► PostCategory
                 ├─ morph many ──► Comment (from Customer)
                 ├─ has tags   ──► (Spatie Tags)
                 └─ has media  ──► Media (post-images: thumb 360×240, og 1200×630)
```

1. An `Author` record exists.
2. A `Post` is created with `author_id`, `post_category_id`, and `is_visible = false`.
3. Images are attached via Spatie MediaLibrary to the `post-images` collection; conversions (`thumb`, `og`) are auto-generated.
4. Tags are attached via Spatie Tags (`HasTags`).
5. Setting `is_visible = true` and a `published_at` date publishes the post.
6. `Comment` records can be added by `Customer`s via the `commentable` polymorphic link (`commentable_type = Post`).

---

### Submitting an Expense

```
Employee
  └─ submits ──► Expense
                   ├─ belongs to ──► Project
                   ├─ has many  ──► ExpenseLine  (individual line items)
                   └─ approved by ► Employee (approver, approved_by FK)

Project
  └─ belongs to ──► Department
       └─ has many ──► Employee
```

1. An `Employee` exists under a `Department`.
2. A `Project` is created under the same `Department`.
3. An `Expense` is created with `employee_id` + `project_id` + status/date/total.
4. One or more `ExpenseLine` rows detail the individual costs (amount, description, category).
5. An approving `Employee` is set via `approved_by` FK, changing status to approved.
6. `Timesheet` records can also be logged by the same employee against the same `Project` or `Task`.

---

## Polymorphic Relationships Summary

| Interface      | Type (`_type`)             | Used by                          |
|----------------|----------------------------|----------------------------------|
| `commentable`  | `Post` or `Product`        | `Comment` morphs to either       |
| `addressable`  | `Order`                    | `OrderAddress` morphs to Order   |
| `addressable`  | `Customer` or `Brand`      | `Address` morphs to Customer/Brand via pivot |

> Note: `addressable` is used in two different ways:
> - **`OrderAddress`** uses `morphTo('addressable')` — one-to-one snapshot on an Order
> - **`Address`** uses `morphToMany/morphedByMany` — many-to-many shared address book for Customers and Brands

---

## Soft Deletes

Models that use `SoftDeletes` (records are never hard-deleted by default):

- `Shop\Customer`
- `Shop\Order`
- `HR\Employee`
- `HR\Project`
